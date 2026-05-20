<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;
use SeoSpider\Auditing\Domain\Model\Issue\SiteIssue;

use SeoSpider\Auditing\Domain\Model\Audit\AuditId;

interface SiteIssueRepository
{
    /** @param SiteIssue[] $issues */
    public function appendIssues(AuditId $auditId, array $issues): void;

    /** @return SiteIssue[] */
    public function findByAudit(AuditId $auditId): array;
}
