<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;

interface SiteIssueRepository
{
    /** @param SiteIssue[] $issues */
    public function appendIssues(AuditId $auditId, array $issues): void;

    /** @return SiteIssue[] */
    public function findByAudit(AuditId $auditId): array;
}
