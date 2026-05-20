<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\AnalyzePage;

use DateTimeImmutable;
use SeoSpider\Audit\Application\Analysis\BufferingIssueCollector;
use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageCrawled;
use SeoSpider\Audit\Domain\Model\Page\PageFetched;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Auditing\Domain\Model\Analysis\Analyzer;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
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

        $collector = $this->runAnalyzers($page);
        $this->persistAuditedPage($page, $collector);

        $pageCrawled = new PageCrawled(
            $page->id(),
            $page->auditId(),
            $page->url(),
            $page->response()->statusCode(),
            count($collector->issues()),
            new DateTimeImmutable(),
        );

        $audit = $this->auditRepository->findById($event->auditId);
        if ($audit === null) {
            $this->eventBus->publish($pageCrawled);
            return;
        }

        if ($event->newUrlsDiscovered > 0) {
            $audit->registerUrlsDiscovered($event->newUrlsDiscovered);
        }
        $audit->registerPageCrawled($collector->errorCount(), $collector->warningCount());
        $this->auditRepository->save($audit);

        $this->eventBus->publish($pageCrawled, ...$audit->pullDomainEvents());
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
            $page->auditId()->value(),
            $page->url()->toString(),
        );
        foreach ($collector->issues() as $issue) {
            $audited->recordIssue($issue);
        }

        $this->auditedPageRepository->save($audited);
    }
}
