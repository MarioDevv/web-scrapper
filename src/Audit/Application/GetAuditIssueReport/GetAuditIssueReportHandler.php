<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Page\IssueRuleCatalog;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;

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
     * Per-page weight cap used to compute each page's individual score.
     * Calibrated so that a page with three top-severity issues
     * (3 × weight 10 = 30) reaches a 0 page score; the audit score is
     * the average of per-page scores, so a single broken page in a
     * large audit only drags the global figure down proportionally.
     */
    private const int MAX_PAGE_PENALTY = 30;

    public function __construct(
        private AuditRepository $auditRepository,
        private IssueReportReader $reader,
    ) {
    }

    public function __invoke(GetAuditIssueReportQuery $query): GetAuditIssueReportResponse
    {
        $auditId = new AuditId($query->auditId);

        if ($this->auditRepository->findById($auditId) === null) {
            throw AuditNotFound::withId($query->auditId);
        }

        $data = $this->reader->read($auditId);

        $groupsByCode = [];
        $severityTotals = [];
        $categoryTotals = [];
        $affectedPageIds = [];
        $totalIssues = 0;
        $weightByPage = [];

        foreach ($data->rows as $row) {
            $code = $row->code;
            $severity = $row->severity;
            $category = $row->category;
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

            $groupsByCode[$code]['pages'][$row->pageId] ??= new AffectedPage(
                pageId: $row->pageId,
                url: $row->pageUrl,
                context: $row->context,
            );

            $severityTotals[$severity] = ($severityTotals[$severity] ?? 0) + 1;
            $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + 1;
            $affectedPageIds[$row->pageId] = true;
            $totalIssues++;
            $weightByPage[$row->pageId] = ($weightByPage[$row->pageId] ?? 0) + $weight;
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
            siteScore: $this->computeSiteScore($data->pageCount, $weightByPage),
        );
    }

    /**
     * Per-page score is 100 × (1 − min(pageWeight, MAX_PAGE_PENALTY) / MAX_PAGE_PENALTY).
     * A clean page contributes 100; a page with three top-severity issues
     * contributes 0. The audit score is the unweighted average across
     * crawled pages so coverage matters: a single broken page in a large
     * audit barely moves the global figure, while a sweep of mid-severity
     * issues across the whole site does.
     *
     * @param array<string, int> $weightByPage
     */
    private function computeSiteScore(int $pageCount, array $weightByPage): int
    {
        if ($pageCount === 0) {
            return 100;
        }

        $sumOfPageScores = 0.0;
        $accountedPages = 0;
        foreach ($weightByPage as $pageWeight) {
            $clamped = min($pageWeight, self::MAX_PAGE_PENALTY);
            $sumOfPageScores += 100 * (1 - $clamped / self::MAX_PAGE_PENALTY);
            $accountedPages++;
        }

        // Pages without any issue never registered in $weightByPage; they
        // each contribute a perfect 100.
        $cleanPages = $pageCount - $accountedPages;
        $sumOfPageScores += 100 * $cleanPages;

        return (int) round($sumOfPageScores / $pageCount);
    }
}
