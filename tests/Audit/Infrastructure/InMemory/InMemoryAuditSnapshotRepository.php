<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Reporting\AuditSnapshot;
use SeoSpider\Auditing\Domain\Model\Reporting\AuditSnapshotRepository;

final class InMemoryAuditSnapshotRepository implements AuditSnapshotRepository
{
    /** @var array<string, AuditSnapshot> */
    private array $snapshots = [];

    public function save(AuditSnapshot $snapshot): void
    {
        $this->snapshots[$snapshot->auditId->value()] = $snapshot;
    }

    public function findByAudit(AuditId $auditId): ?AuditSnapshot
    {
        return $this->snapshots[$auditId->value()] ?? null;
    }
}
