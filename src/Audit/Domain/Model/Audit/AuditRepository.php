<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

interface AuditRepository
{
    public function save(Audit $audit): void;

    public function findById(AuditId $id): ?Audit;

    public function nextId(): AuditId;
}
