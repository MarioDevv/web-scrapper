<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\CrawlPage;

use SeoSpider\Audit\Domain\Model\Analyzer\Analyzer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\HtmlParser;
use SeoSpider\Audit\Domain\Model\HttpClient;
use SeoSpider\Audit\Domain\Model\HttpRequestFailed;
use SeoSpider\Audit\Domain\Model\Page\Directive;
use SeoSpider\Audit\Domain\Model\Page\DirectiveSource;
use SeoSpider\Audit\Domain\Model\Page\Fingerprint;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageFailed;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Shared\Domain\Bus\EventBus;
use DateTimeImmutable;

final readonly class CrawlPageHandler
{
    /** @param Analyzer[] $analyzers */
    public function __construct(
        private AuditRepository $auditRepository,
        private PageRepository $pageRepository,
        private HttpClient $httpClient,
        private HtmlParser $htmlParser,
        private Frontier $frontier,
        private EventBus $eventBus,
        private array $analyzers = [],
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

        $this->frontier->markVisited($auditId, $url);

        try {
            $result = $this->httpClient->followRedirects(
                $url,
                $audit->configuration()->customUserAgent,
            );
        } catch (HttpRequestFailed $e) {
            $this->handleFailure($auditId, $url, $e->getMessage());
            return;
        }

        $page = Page::fromCrawl(
            id: $this->pageRepository->nextId(),
            auditId: $auditId,
            url: $url,
            response: $result['response'],
            redirectChain: $result['chain'],
            crawlDepth: $command->depth,
        );

        if ($page->isHtml() && $result['response']->body() !== null) {
            $this->enrichHtmlPage($page, $result['response']->body(), $url);
            $this->discoverUrls($page, $auditId, $command->depth);
        }

        $this->runAnalyzers($page);
        $page->markAsAnalyzed();

        $this->pageRepository->save($page);

        $audit->registerPageCrawled($page->errorCount(), $page->warningCount());
        $this->auditRepository->save($audit);

        $this->eventBus->publish(
            ...$page->pullDomainEvents(),
            ...$audit->pullDomainEvents(),
        );
    }

    private function enrichHtmlPage(Page $page, string $html, Url $pageUrl): void
    {
        $page->enrichWithMetadata($this->htmlParser->extractMetadata($html));
        $page->enrichWithLinks($this->htmlParser->extractLinks($html, $pageUrl));
        $page->enrichWithHreflangs($this->htmlParser->extractHreflangs($html));

        $htmlDirective = $this->htmlParser->extractDirectives($html);
        $headerDirective = $this->extractDirectivesFromHeaders($page);
        $page->enrichWithDirectives(Directive::merge($htmlDirective, $headerDirective));

        $cleanContent = $this->htmlParser->extractCleanContent($html);
        if ($cleanContent !== '') {
            $page->enrichWithFingerprint(Fingerprint::fromContent($cleanContent));
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

    private function discoverUrls(Page $page, AuditId $auditId, int $currentDepth): void
    {
        $audit = $this->auditRepository->findById($auditId);
        if ($audit === null) {
            return;
        }

        $maxDepth = $audit->configuration()->maxDepth;
        $nextDepth = $currentDepth + 1;

        if ($nextDepth > $maxDepth) {
            return;
        }

        $newUrls = 0;
        foreach ($page->internalLinks() as $link) {
            if (!$link->isFollowable()) {
                continue;
            }

            $enqueued = $this->frontier->enqueue($auditId, $link->targetUrl(), $nextDepth);
            if ($enqueued) {
                $newUrls++;
            }
        }

        if ($newUrls > 0) {
            $audit->registerUrlsDiscovered($newUrls);
            $this->auditRepository->save($audit);
        }
    }

    private function runAnalyzers(Page $page): void
    {
        foreach ($this->analyzers as $analyzer) {
            $analyzer->analyze($page);
        }
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
