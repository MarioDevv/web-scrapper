<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\CrawlPage;

use DateTimeImmutable;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\HtmlParser;
use SeoSpider\Audit\Domain\Model\HttpClient;
use SeoSpider\Audit\Domain\Model\HttpRequestFailed;
use SeoSpider\Audit\Domain\Model\Page\Directive;
use SeoSpider\Audit\Domain\Model\Page\DirectiveSource;
use SeoSpider\Audit\Domain\Model\Page\Fingerprint;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageFailed;
use SeoSpider\Audit\Domain\Model\Page\PageFetched;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlDiscoverer;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class CrawlPageHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private PageRepository $pageRepository,
        private HttpClient $httpClient,
        private HtmlParser $htmlParser,
        private UrlDiscoverer $urlDiscoverer,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(CrawlPageCommand $command): void
    {
        $auditId = new AuditId($command->auditId);
        $url = Url::fromString($command->url);

        $audit = $this->auditRepository->findById($auditId);
        if ($audit === null || !$audit->canAcceptMorePages()) {
            return;
        }

        try {
            $result = $this->httpClient->followRedirects(
                $url,
                $audit->configuration()->customUserAgent,
            );
        } catch (HttpRequestFailed $e) {
            $this->handleFetchFailure($command, $e->getMessage());
            return;
        }

        $this->processFetchedPage($command, $result['response'], $result['chain']);
    }

    /**
     * Post-fetch pipeline. Fetches bytes are already in memory; this step
     * enriches the page from HTML, enqueues discovered URLs, persists the
     * page and announces PageFetched. The analyzer pipeline and the audit
     * stats update live behind that event so adding analyzers doesn't
     * touch this class and re-analysing doesn't need a fresh fetch.
     */
    public function processFetchedPage(
        CrawlPageCommand $command,
        PageResponse $response,
        RedirectChain $chain,
    ): void {
        $auditId = new AuditId($command->auditId);
        $url = Url::fromString($command->url);

        $audit = $this->auditRepository->findById($auditId);
        if ($audit === null || !$audit->canAcceptMorePages()) {
            return;
        }

        $page = Page::fromCrawl(
            id: $this->pageRepository->nextId(),
            auditId: $auditId,
            url: $url,
            response: $response,
            redirectChain: $chain,
            crawlDepth: $command->depth,
        );

        if ($page->isHtml() && $response->body() !== null) {
            $this->enrichHtmlPage($page, $response->body(), $url);
            $newUrls = $this->urlDiscoverer->discoverFrom(
                $page,
                $auditId,
                $command->depth,
                $audit->configuration(),
            );
        } else {
            $newUrls = 0;
        }

        $this->pageRepository->save($page);

        $this->eventBus->publish(new PageFetched(
            pageId: $page->id(),
            auditId: $auditId,
            newUrlsDiscovered: $newUrls,
            occurredAt: new DateTimeImmutable(),
        ));
    }

    public function handleFetchFailure(CrawlPageCommand $command, string $reason): void
    {
        $auditId = new AuditId($command->auditId);
        $url = Url::fromString($command->url);

        $this->handleFailure($auditId, $url, $reason);
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

    private function handleFailure(AuditId $auditId, Url $url, string $reason): void
    {
        $audit = $this->auditRepository->findById($auditId);
        if ($audit === null) {
            return;
        }

        $audit->registerPageFailed();
        $this->auditRepository->save($audit);

        $this->eventBus->publish(
            new PageFailed(
                pageId: $this->pageRepository->nextId(),
                auditId: $auditId,
                url: $url,
                reason: $reason,
                occurredAt: new DateTimeImmutable(),
            ),
            ...$audit->pullDomainEvents(),
        );
    }
}
