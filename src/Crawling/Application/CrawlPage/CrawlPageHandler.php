<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application\CrawlPage;

use DateTimeImmutable;
use SeoSpider\Crawling\Application\AuditCoordinator;
use SeoSpider\Crawling\Domain\Model\CrawlPolicy;
use SeoSpider\Crawling\Application\HtmlParser;
use SeoSpider\Crawling\Application\HttpClient;
use SeoSpider\Crawling\Application\LegacyPageToCrawledPage;
use SeoSpider\Crawling\Application\UrlDiscoverer;
use SeoSpider\Crawling\Domain\Model\HttpRequestFailed;
use SeoSpider\Crawling\Domain\Model\Page\Directive;
use SeoSpider\Crawling\Domain\Model\Page\DirectiveSource;
use SeoSpider\Crawling\Domain\Model\Page\Fingerprint;
use SeoSpider\Crawling\Domain\Model\Page\Page;
use SeoSpider\Crawling\Domain\Model\Page\PageFailed;
use SeoSpider\Crawling\Domain\Model\Page\PageRepository;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Crawling\Domain\Model\Url;
use SeoSpider\Shared\Domain\Bus\EventBus;
use SeoSpider\Shared\Integration\PageWasCrawled;

final readonly class CrawlPageHandler
{
    public function __construct(
        private AuditCoordinator $auditCoordinator,
        private PageRepository $pageRepository,
        private HttpClient $httpClient,
        private HtmlParser $htmlParser,
        private UrlDiscoverer $urlDiscoverer,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(CrawlPageCommand $command): void
    {
        $url = Url::fromString($command->url);

        $snapshot = $this->auditCoordinator->snapshot($command->auditId);
        if ($snapshot === null || !$snapshot->canAcceptMorePages) {
            return;
        }

        try {
            $result = $this->httpClient->followRedirects($url, $snapshot->config->customUserAgent);
        } catch (HttpRequestFailed $e) {
            $this->handleFetchFailure($command, $e->getMessage());
            return;
        }

        $this->processFetchedPage($command, $result['response'], $result['chain']);
    }

    public function processFetchedPage(
        CrawlPageCommand $command,
        PageResponse $response,
        RedirectChain $chain,
    ): void {
        $url = Url::fromString($command->url);

        $snapshot = $this->auditCoordinator->snapshot($command->auditId);
        if ($snapshot === null || !$snapshot->canAcceptMorePages) {
            return;
        }

        $page = Page::fromCrawl(
            id: $this->pageRepository->nextId(),
            auditId: $command->auditId,
            url: $url,
            response: $response,
            redirectChain: $chain,
            crawlDepth: $command->depth,
        );

        if ($page->isHtml() && $response->body() !== null) {
            $this->enrichHtmlPage($page, $response->body(), $url);
            $newUrls = $this->urlDiscoverer->discoverFrom(
                (new LegacyPageToCrawledPage())($page),
                $command->auditId,
                $command->depth,
                new CrawlPolicy(
                    maxDepth: $snapshot->config->maxDepth,
                    crawlResources: $snapshot->config->crawlResources,
                ),
            );
        } else {
            $newUrls = 0;
        }

        $this->pageRepository->save($page);

        $this->eventBus->publish(
            new PageWasCrawled(
                pageId: $page->id()->value(),
                auditId: $command->auditId,
                url: $page->url()->toString(),
                newUrlsDiscovered: $newUrls,
                occurredAt: new DateTimeImmutable(),
            ),
        );
    }

    public function handleFetchFailure(CrawlPageCommand $command, string $reason): void
    {
        $url = Url::fromString($command->url);
        $this->handleFailure($command->auditId, $url, $reason);
    }

    private function enrichHtmlPage(Page $page, string $html, Url $pageUrl): void
    {
        $parsed = $this->htmlParser->parse($html, $pageUrl);

        $page->enrichWithMetadata($parsed->metadata);
        $page->enrichWithLinks($parsed->links);
        $page->enrichWithHreflangs($parsed->hreflangs);

        $headerDirective = $this->extractDirectivesFromHeaders($page);
        $page->enrichWithDirectives(Directive::merge($parsed->directive, $headerDirective));

        if ($parsed->cleanContent !== '') {
            $page->enrichWithFingerprint(Fingerprint::fromContent($parsed->cleanContent));
        }
    }

    private function extractDirectivesFromHeaders(Page $page): Directive
    {
        $xRobotsTag = $page->response()->header('X-Robots-Tag');
        if ($xRobotsTag === null) {
            return new Directive();
        }

        $lower = strtolower($xRobotsTag);

        return new Directive(
            noindex: str_contains($lower, 'noindex'),
            nofollow: str_contains($lower, 'nofollow'),
            noarchive: str_contains($lower, 'noarchive'),
            nosnippet: str_contains($lower, 'nosnippet'),
            noimageindex: str_contains($lower, 'noimageindex'),
            source: DirectiveSource::HTTP_HEADER,
        );
    }

    private function handleFailure(string $auditId, Url $url, string $reason): void
    {
        $this->auditCoordinator->registerPageFailed($auditId);

        $this->eventBus->publish(
            new PageFailed(
                pageId: $this->pageRepository->nextId(),
                auditId: $auditId,
                url: $url,
                reason: $reason,
                occurredAt: new DateTimeImmutable(),
            ),
        );
    }
}
