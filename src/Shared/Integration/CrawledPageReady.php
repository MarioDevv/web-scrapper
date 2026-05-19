<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Integration;

use DateTimeImmutable;
use SeoSpider\Shared\Domain\DomainEvent;

/**
 * Pivotal seam event. Crawling (Supplier) publishes this; Auditing
 * (Customer) subscribes via an ACL — wired in Phase 3. Lives in Shared
 * because it is the Published Language: both contexts may depend on it,
 * neither on the other.
 */
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
