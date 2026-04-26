<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

interface AuditSnapshotRepository
{
    public function save(AuditSnapshot $snapshot): void;

    public function findByAudit(AuditId $auditId): ?AuditSnapshot;
}
