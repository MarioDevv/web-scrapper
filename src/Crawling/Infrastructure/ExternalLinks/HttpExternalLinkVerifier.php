<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Infrastructure\ExternalLinks;

use SeoSpider\Crawling\Domain\Model\ExternalLink\ExternalLinkRepository;
use SeoSpider\Crawling\Domain\Model\ExternalLink\ExternalLinkVerifier;
use SeoSpider\Crawling\Application\HttpClient;
use SeoSpider\Crawling\Domain\Model\HttpRequestFailed;
use SeoSpider\Crawling\Domain\Model\Page\Page;
use SeoSpider\Crawling\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\Page\PageRepository;
use SeoSpider\Crawling\Domain\Model\Url;

final readonly class HttpExternalLinkVerifier implements ExternalLinkVerifier
{
    public function __construct(
        private PageRepository $pageRepository,
        private ExternalLinkRepository $externalLinkRepository,
        private HttpClient $httpClient,
    ) {
    }

    public function verify(string $auditId, ?string $userAgent = null): int
    {
        $references = $this->collectExternalReferences($auditId);
        $probed = 0;

        foreach ($references as $group) {
            /** @var Url $url */
            $url = $group['url'];

            if ($this->externalLinkRepository->exists($auditId, $url)) {
                continue;
            }

            $result = $this->probe($url, $userAgent);
            $probed++;

            foreach ($group['references'] as $ref) {
                $this->externalLinkRepository->save(
                    $auditId,
                    $url,
                    $result['statusCode'],
                    $result['responseTime'],
                    $result['error'],
                    $ref['sourcePageId'],
                    $ref['anchorText'],
                );
            }
        }

        return $probed;
    }

    /**
     * @return array<string, array{url: Url, references: list<array{sourcePageId: PageId, anchorText: ?string}>}>
     */
    private function collectExternalReferences(string $auditId): array
    {
        $grouped = [];

        /** @var Page[] $pages */
        $pages = $this->pageRepository->findByAudit($auditId);

        foreach ($pages as $page) {
            foreach ($page->links() as $link) {
                if (!$link->isExternal() || !$link->isAnchor()) {
                    continue;
                }

                $key = $link->targetUrl()->toString();
                if (!isset($grouped[$key])) {
                    $grouped[$key] = ['url' => $link->targetUrl(), 'references' => []];
                }

                $grouped[$key]['references'][] = [
                    'sourcePageId' => $page->id(),
                    'anchorText' => $link->anchorText(),
                ];
            }
        }

        return $grouped;
    }

    /** @return array{statusCode: int, responseTime: float, error: ?string} */
    private function probe(Url $url, ?string $userAgent): array
    {
        try {
            $result = $this->httpClient->head($url, $userAgent);

            return [
                'statusCode' => $result['statusCode'],
                'responseTime' => $result['responseTime'],
                'error' => null,
            ];
        } catch (HttpRequestFailed $e) {
            return [
                'statusCode' => 0,
                'responseTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
