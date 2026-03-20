<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

use DateTimeImmutable;
use SeoSpider\Shared\Domain\DomainEvent;

final readonly class AuditCompleted implements DomainEvent
{
    public function __construct(
        public AuditId $auditId,
        public AuditStatistics $statistics,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
