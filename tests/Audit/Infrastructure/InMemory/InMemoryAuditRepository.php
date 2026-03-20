<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\Audit\Audit;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;

final class InMemoryAuditRepository implements AuditRepository
{
    /** @var array<string, Audit> */
    private array $audits = [];

    public function save(Audit $audit): void
    {
        $this->audits[$audit->id()->value()] = $audit;
    }

    public function findById(AuditId $id): ?Audit
    {
        return $this->audits[$id->value()] ?? null;
    }

    public function nextId(): AuditId
    {
        return AuditId::generate();
    }
}
