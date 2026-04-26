<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;

/**
 * Read port for the site-wide issue report. Implementations are free
 * to bypass the Page aggregate and project the data directly from
 * storage; the report only needs the issue list and the total page
 * count, not the full page state.
 */
interface IssueReportReader
{
    public function read(AuditId $auditId): IssueReportData;
}
