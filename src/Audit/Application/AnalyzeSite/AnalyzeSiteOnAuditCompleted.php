<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\AnalyzeSite;

use SeoSpider\Audit\Application\Analysis\LegacySiteContext;
use SeoSpider\Crawling\Domain\Model\Page\PageRepository;
use SeoSpider\Auditing\Domain\Model\Analysis\SiteAnalyzer;
use SeoSpider\Auditing\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
use SeoSpider\Auditing\Domain\Model\Reporting\SiteIssueRepository;

final readonly class AnalyzeSiteOnAuditCompleted
{
    /** @param SiteAnalyzer[] $siteAnalyzers */
    public function __construct(
        private PageRepository $pageRepository,
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

        $pages = $this->pageRepository->findByAudit($event->auditId->value());
        if ($pages === []) {
            return;
        }

        $context = new LegacySiteContext(
            auditId: $event->auditId->value(),
            seedUrl: $audit->configuration()->seedUrl,
            pages: $pages,
        );

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
