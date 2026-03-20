<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

use DateTimeImmutable;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Shared\Domain\DomainEvent;

final readonly class AuditStarted implements DomainEvent
{
    public function __construct(
        public AuditId $auditId,
        public Url $seedUrl,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
