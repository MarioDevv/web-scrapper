<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

final readonly class GetAuditIssueReportResponse
{
    /**
     * @param array<string, int> $severityTotals     keyed by severity value
     * @param array<string, int> $categoryTotals     keyed by category value
     * @param IssueGroup[]       $groups             sorted by severity then count desc
     */
    public function __construct(
        public string $auditId,
        public int $totalIssues,
        public int $affectedPages,
        public array $severityTotals,
        public array $categoryTotals,
        public array $groups,
    ) {
    }
}
