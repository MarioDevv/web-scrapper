<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\AnalyzeSite;

use SeoSpider\Audit\Domain\Model\Analyzer\SiteAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\SiteAuditContext;
use SeoSpider\Audit\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;

/**
 * Site-wide analysis phase. After the per-page pipeline has finished
 * (signalled by AuditCompleted), this reactor loads every page of the
 * audit, runs the SiteAnalyzer pipeline against the complete graph,
 * and persists only the newly produced issues. Existing issues stay
 * intact — appendIssues skips the DELETE+INSERT path that save() uses.
 */
final readonly class AnalyzeSiteOnAuditCompleted
{
    /** @param SiteAnalyzer[] $siteAnalyzers */
    public function __construct(
        private PageRepository $pageRepository,
        private AuditRepository $auditRepository,
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

        $context = new SiteAuditContext(
            auditId: $event->auditId,
            seedUrl: $audit->configuration()->seedUrl,
            pages: $pages,
        );

        $existingIssueIds = $this->snapshotExistingIssueIds($pages);

        foreach ($this->siteAnalyzers as $analyzer) {
            $analyzer->analyze($context);
        }

        foreach ($pages as $page) {
            $newIssues = $this->newIssuesFor($page, $existingIssueIds[$page->id()->value()] ?? []);
            if ($newIssues !== []) {
                $this->pageRepository->appendIssues($page->id(), $newIssues);
            }
        }
    }

    /**
     * @param Page[] $pages
     * @return array<string, array<string, true>>
     */
    private function snapshotExistingIssueIds(array $pages): array
    {
        $snapshot = [];
        foreach ($pages as $page) {
            $ids = [];
            foreach ($page->issues() as $issue) {
                $ids[$issue->id()->value()] = true;
            }
            $snapshot[$page->id()->value()] = $ids;
        }

        return $snapshot;
    }

    /**
     * @param array<string, true> $existingIds
     * @return Issue[]
     */
    private function newIssuesFor(Page $page, array $existingIds): array
    {
        $new = [];
        foreach ($page->issues() as $issue) {
            if (!isset($existingIds[$issue->id()->value()])) {
                $new[] = $issue;
            }
        }

        return $new;
    }
}
