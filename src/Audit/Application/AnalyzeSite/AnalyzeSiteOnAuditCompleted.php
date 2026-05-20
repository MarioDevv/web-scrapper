<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\AnalyzeSite;

use SeoSpider\Audit\Application\Analysis\LegacySiteContext;
use SeoSpider\Audit\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Audit\Domain\Model\Page\SiteIssueRepository;
use SeoSpider\Auditing\Domain\Model\Analysis\SiteAnalyzer;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;

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

        $pages = $this->pageRepository->findByAudit($event->auditId);
        if ($pages === []) {
            return;
        }

        $context = new LegacySiteContext(
            auditId: $event->auditId->value(),
            seedUrl: $audit->configuration()->seedUrl->toString(),
            pages: $pages,
        );

        foreach ($this->siteAnalyzers as $analyzer) {
            $analyzer->analyze($context);
        }

        foreach ($pages as $page) {
            if ($page->issues() === []) {
                continue;
            }
            $audited = AuditedPage::forUrl(
                $event->auditId->value(),
                $page->url()->toString(),
            );
            foreach ($page->issues() as $issue) {
                $audited->recordIssue($issue);
            }
            $this->auditedPageRepository->save($audited);
        }

        if ($context->siteIssues() !== []) {
            $this->siteIssueRepository->appendIssues($event->auditId, $context->siteIssues());
        }
    }
}
