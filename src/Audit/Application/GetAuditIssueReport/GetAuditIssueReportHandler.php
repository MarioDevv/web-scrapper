<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Page\IssueRuleCatalog;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;

/**
 * Read model that projects an audit's per-page issues into a site-wide
 * report grouped by issue code. Each group is enriched with static
 * prose (title/why/how) from the rule catalog so the UI does not need
 * to know how to render individual codes.
 */
final readonly class GetAuditIssueReportHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private PageRepository $pageRepository,
    ) {
    }

    public function __invoke(GetAuditIssueReportQuery $query): GetAuditIssueReportResponse
    {
        $auditId = new AuditId($query->auditId);

        if ($this->auditRepository->findById($auditId) === null) {
            throw AuditNotFound::withId($query->auditId);
        }

        $pages = $this->pageRepository->findByAudit($auditId);

        $groupsByCode = [];
        $severityTotals = [];
        $categoryTotals = [];
        $affectedPageIds = [];
        $totalIssues = 0;

        foreach ($pages as $page) {
            foreach ($page->issues() as $issue) {
                $code = $issue->code();
                $severity = $issue->severity()->value;
                $category = $issue->category()->value;

                if (!isset($groupsByCode[$code])) {
                    $groupsByCode[$code] = [
                        'code' => $code,
                        'category' => $category,
                        'severity' => $severity,
                        'count' => 0,
                        'pages' => [],
                    ];
                }

                $groupsByCode[$code]['count']++;

                $pageId = $page->id()->value();
                $groupsByCode[$code]['pages'][$pageId] ??= new AffectedPage(
                    pageId: $pageId,
                    url: $page->url()->toString(),
                    context: $issue->context(),
                );

                $severityTotals[$severity] = ($severityTotals[$severity] ?? 0) + 1;
                $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + 1;
                $affectedPageIds[$pageId] = true;
                $totalIssues++;
            }
        }

        $groups = [];
        foreach ($groupsByCode as $raw) {
            $rule = IssueRuleCatalog::forCode($raw['code']);
            $affected = array_values($raw['pages']);
            usort($affected, static fn(AffectedPage $a, AffectedPage $b) => $a->url <=> $b->url);

            $groups[] = new IssueGroup(
                code: $raw['code'],
                category: $raw['category'],
                severity: $raw['severity'],
                title: $rule?->title,
                summary: $rule?->summary,
                why: $rule?->why,
                how: $rule?->how,
                source: $rule?->source,
                count: $raw['count'],
                affectedPageCount: count($raw['pages']),
                affectedPages: $affected,
            );
        }

        usort($groups, static function (IssueGroup $a, IssueGroup $b): int {
            $byRank = IssueSeverity::from($a->severity)->rank() <=> IssueSeverity::from($b->severity)->rank();
            if ($byRank !== 0) {
                return $byRank;
            }

            return $b->affectedPageCount <=> $a->affectedPageCount;
        });

        return new GetAuditIssueReportResponse(
            auditId: $query->auditId,
            totalIssues: $totalIssues,
            affectedPages: count($affectedPageIds),
            severityTotals: $severityTotals,
            categoryTotals: $categoryTotals,
            groups: $groups,
        );
    }
}
