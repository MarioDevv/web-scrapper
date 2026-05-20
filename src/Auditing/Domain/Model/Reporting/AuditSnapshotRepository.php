<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

use SeoSpider\Auditing\Domain\Model\Audit\AuditId;

interface AuditSnapshotRepository
{
    public function save(AuditSnapshot $snapshot): void;

    public function findByAudit(AuditId $auditId): ?AuditSnapshot;
}
