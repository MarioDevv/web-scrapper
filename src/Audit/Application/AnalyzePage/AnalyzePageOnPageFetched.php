<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\AnalyzePage;

use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Audit\Application\Analysis\PageBackedIssueCollector;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Auditing\Domain\Model\Analysis\Analyzer;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageFetched;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class AnalyzePageOnPageFetched
{
    /** @param Analyzer[] $analyzers */
    public function __construct(
        private PageRepository $pageRepository,
        private AuditRepository $auditRepository,
        private EventBus $eventBus,
        private AuditedPageRepository $auditedPageRepository,
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
        $this->persistAuditedPage($page);

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
        if ($this->analyzers === []) {
            return;
        }

        $signals = new LegacyPageToPageSignals($page);
        $collector = new PageBackedIssueCollector($page);
        foreach ($this->analyzers as $analyzer) {
            $analyzer->analyze($signals, $collector);
        }
    }

    private function persistAuditedPage(Page $page): void
    {
        $audited = AuditedPage::forUrl(
            $page->auditId()->value(),
            $page->url()->toString(),
        );
        foreach ($page->issues() as $issue) {
            $audited->recordIssue($issue);
        }

        $this->auditedPageRepository->save($audited);
    }
}
