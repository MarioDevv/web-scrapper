<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\AnalyzePage;

use SeoSpider\Auditing\Application\Analysis\BufferingIssueCollector;
use SeoSpider\Auditing\Infrastructure\Acl\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\Analyzer;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
use SeoSpider\Crawling\Domain\Model\Page\Page;
use SeoSpider\Crawling\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\Page\PageRepository;
use SeoSpider\Shared\Domain\Bus\EventBus;
use SeoSpider\Shared\Integration\PageWasCrawled;

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

    public function __invoke(PageWasCrawled $event): void
    {
        $page = $this->pageRepository->findById(new PageId($event->pageId));
        if ($page === null) {
            return;
        }

        $collector = $this->runAnalyzers($page);
        $this->persistAuditedPage($page, $collector);

        $audit = $this->auditRepository->findById(new AuditId($event->auditId));
        if ($audit === null) {
            return;
        }

        if ($event->newUrlsDiscovered > 0) {
            $audit->registerUrlsDiscovered($event->newUrlsDiscovered);
        }
        $audit->registerPageCrawled($collector->errorCount(), $collector->warningCount());
        $this->auditRepository->save($audit);

        $this->eventBus->publish(...$audit->pullDomainEvents());
    }

    private function runAnalyzers(Page $page): BufferingIssueCollector
    {
        $collector = new BufferingIssueCollector();
        if ($this->analyzers === []) {
            return $collector;
        }

        $signals = new LegacyPageToPageSignals($page);
        foreach ($this->analyzers as $analyzer) {
            $analyzer->analyze($signals, $collector);
        }

        return $collector;
    }

    private function persistAuditedPage(Page $page, BufferingIssueCollector $collector): void
    {
        $audited = AuditedPage::forUrl(
            $page->auditId(),
            $page->url()->toString(),
        );
        foreach ($collector->issues() as $issue) {
            $audited->recordIssue($issue);
        }

        $this->auditedPageRepository->save($audited);
    }
}
