<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Integration;

use DateTimeImmutable;
use SeoSpider\Shared\Domain\DomainEvent;

final readonly class CrawledPageReady implements DomainEvent
{
    public function __construct(
        public CrawledPagePayload $payload,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
