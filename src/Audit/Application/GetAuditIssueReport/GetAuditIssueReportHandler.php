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
 * to know how to render individual codes. The handler also computes a
 * 0–100 site score from the per-rule weights in the catalog.
 */
final readonly class GetAuditIssueReportHandler
{
    /**
     * Per-page weight cap used to normalise the score: equal to the
     * maximum rule weight (10), so a single ERROR-class issue costs at
     * most 10/(pages×10) of the score, and a page with worst-case
     * issues clamps at a 10-point penalty rather than runaway negatives.
     */
    private const int MAX_WEIGHT_PER_PAGE = 10;

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
        $totalWeight = 0;

        foreach ($pages as $page) {
            foreach ($page->issues() as $issue) {
                $code = $issue->code();
                $severity = $issue->severity()->value;
                $category = $issue->category()->value;
                $rule = IssueRuleCatalog::forCode($code);
                $weight = $rule?->weight() ?? 0;

                if (!isset($groupsByCode[$code])) {
                    $groupsByCode[$code] = [
                        'code' => $code,
                        'category' => $category,
                        'severity' => $severity,
                        'weight' => $weight,
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
                $totalWeight += $weight;
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
                weight: $raw['weight'],
            );
        }

        usort($groups, static function (IssueGroup $a, IssueGroup $b): int {
            $byImpact = ($b->weight * $b->affectedPageCount) <=> ($a->weight * $a->affectedPageCount);
            if ($byImpact !== 0) {
                return $byImpact;
            }

            $byRank = IssueSeverity::from($a->severity)->rank() <=> IssueSeverity::from($b->severity)->rank();
            if ($byRank !== 0) {
                return $byRank;
            }

            return $b->count <=> $a->count;
        });

        return new GetAuditIssueReportResponse(
            auditId: $query->auditId,
            totalIssues: $totalIssues,
            affectedPages: count($affectedPageIds),
            severityTotals: $severityTotals,
            categoryTotals: $categoryTotals,
            groups: $groups,
            siteScore: $this->computeSiteScore(count($pages), $totalWeight),
        );
    }

    private function computeSiteScore(int $pageCount, int $totalWeight): int
    {
        if ($pageCount === 0) {
            return 100;
        }

        $denominator = $pageCount * self::MAX_WEIGHT_PER_PAGE;
        $raw = 100 * (1 - $totalWeight / $denominator);

        return (int) round(max(0, min(100, $raw)));
    }
}
