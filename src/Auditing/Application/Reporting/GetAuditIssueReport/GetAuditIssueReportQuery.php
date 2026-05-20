<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport;

final readonly class GetAuditIssueReportQuery
{
    public function __construct(public string $auditId)
    {
    }
}
