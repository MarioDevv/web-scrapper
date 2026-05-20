<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reactors;

use SeoSpider\Auditing\Application\Analysis\BufferingIssueCollector;
use SeoSpider\Auditing\Domain\Model\Analysis\Analyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\PageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\PageSignalsReader;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
use SeoSpider\Shared\Domain\Bus\EventBus;
use SeoSpider\Shared\Integration\PageWasCrawled;

final readonly class AnalyzePageOnPageWasCrawled
{
    /** @param Analyzer[] $analyzers */
    public function __construct(
        private PageSignalsReader $pageSignalsReader,
        private AuditRepository $auditRepository,
        private EventBus $eventBus,
        private AuditedPageRepository $auditedPageRepository,
        private array $analyzers = [],
    ) {
    }

    public function __invoke(PageWasCrawled $event): void
    {
        $signals = $this->pageSignalsReader->findById($event->pageId);
        if ($signals === null) {
            return;
        }

        $collector = $this->runAnalyzers($signals);
        $this->persistAuditedPage($event->auditId, $event->url, $collector);

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

    private function runAnalyzers(PageSignals $signals): BufferingIssueCollector
    {
        $collector = new BufferingIssueCollector();
        foreach ($this->analyzers as $analyzer) {
            $analyzer->analyze($signals, $collector);
        }

        return $collector;
    }

    private function persistAuditedPage(string $auditId, string $url, BufferingIssueCollector $collector): void
    {
        $audited = AuditedPage::forUrl($auditId, $url);
        foreach ($collector->issues() as $issue) {
            $audited->recordIssue($issue);
        }

        $this->auditedPageRepository->save($audited);
    }
}
