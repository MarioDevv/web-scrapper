<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Audit;

interface AuditRepository
{
    public function save(Audit $audit): void;

    public function findById(AuditId $id): ?Audit;

    public function nextId(): AuditId;

    /**
     * Most recent completed audit whose seed URL has the given host,
     * excluding the audit identified by $excluding. Used by the
     * comparison feature to default the "compare with previous" target.
     */
    public function findPreviousCompletedByHost(string $host, AuditId $excluding): ?Audit;
}
