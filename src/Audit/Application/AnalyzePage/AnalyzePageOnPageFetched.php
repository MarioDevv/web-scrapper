<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\AnalyzePage;

use SeoSpider\Audit\Domain\Model\Analyzer\Analyzer;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageFetched;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Shared\Domain\Bus\EventBus;

/**
 * Event-driven analyzer phase. The crawl handler persists the fetched page
 * and announces PageFetched; this reactor picks it up, runs the analyzer
 * pipeline, records the resulting issue counts on the audit, saves both
 * aggregates, and publishes the downstream events (PageCrawled from the
 * page, any audit transitions like AuditCompleted when maxPages is hit).
 *
 * Keeping the pipeline behind the bus means adding a new analyzer does not
 * touch the crawl handler, and re-running analyzers over an already-crawled
 * page only needs to publish PageFetched for it.
 */
final readonly class AnalyzePageOnPageFetched
{
    /** @param Analyzer[] $analyzers */
    public function __construct(
        private PageRepository $pageRepository,
        private AuditRepository $auditRepository,
        private EventBus $eventBus,
        private array $analyzers = [],
    ) {
    }

    public function __invoke(PageFetched $event): void
    {
        $page = $this->pageRepository->findById($event->pageId);
        if ($page === null) {
            return;
        }

        $this->runAnalyzers($page);
        $page->markAsAnalyzed();
        $this->pageRepository->save($page);

        $audit = $this->auditRepository->findById($event->auditId);
        if ($audit === null) {
            $this->eventBus->publish(...$page->pullDomainEvents());
            return;
        }

        if ($event->newUrlsDiscovered > 0) {
            $audit->registerUrlsDiscovered($event->newUrlsDiscovered);
        }
        $audit->registerPageCrawled($page->errorCount(), $page->warningCount());
        $this->auditRepository->save($audit);

        $this->eventBus->publish(
            ...$page->pullDomainEvents(),
            ...$audit->pullDomainEvents(),
        );
    }

    private function runAnalyzers(Page $page): void
    {
        foreach ($this->analyzers as $analyzer) {
            $analyzer->analyze($page);
        }
    }
}
