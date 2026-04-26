<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

final readonly class IssueReportData
{
    /** @param IssueReportRow[] $rows */
    public function __construct(
        public array $rows,
        public int $pageCount,
    ) {
    }
}
