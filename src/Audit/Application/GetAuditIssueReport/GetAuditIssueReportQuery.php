<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

final readonly class GetAuditIssueReportQuery
{
    public function __construct(public string $auditId)
    {
    }
}
