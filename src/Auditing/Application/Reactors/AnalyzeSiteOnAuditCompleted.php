<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reactors;

use SeoSpider\Auditing\Domain\Model\Analysis\SiteAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\SiteContextFactory;
use SeoSpider\Auditing\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
use SeoSpider\Auditing\Domain\Model\Reporting\SiteIssueRepository;

final readonly class AnalyzeSiteOnAuditCompleted
{
    /** @param SiteAnalyzer[] $siteAnalyzers */
    public function __construct(
        private SiteContextFactory $siteContextFactory,
        private AuditRepository $auditRepository,
        private SiteIssueRepository $siteIssueRepository,
        private AuditedPageRepository $auditedPageRepository,
        private array $siteAnalyzers = [],
    ) {
    }

    public function __invoke(AuditCompleted $event): void
    {
        if ($this->siteAnalyzers === []) {
            return;
        }

        $audit = $this->auditRepository->findById($event->auditId);
        if ($audit === null) {
            return;
        }

        $context = $this->siteContextFactory->forAudit(
            $event->auditId->value(),
            $audit->configuration()->seedUrl,
        );
        if ($context === null) {
            return;
        }

        foreach ($this->siteAnalyzers as $analyzer) {
            $analyzer->analyze($context);
        }

        foreach ($context->bufferedPageIssues() as $pageUrl => $issues) {
            $existing = $this->auditedPageRepository->findByAuditAndUrl(
                $event->auditId->value(),
                $pageUrl,
            ) ?? AuditedPage::forUrl($event->auditId->value(), $pageUrl);

            foreach ($issues as $issue) {
                $existing->recordIssue($issue);
            }
            $this->auditedPageRepository->save($existing);
        }

        if ($context->siteIssues() !== []) {
            $this->siteIssueRepository->appendIssues($event->auditId, $context->siteIssues());
        }
    }
}
