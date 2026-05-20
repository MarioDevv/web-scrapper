<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Integration;

use DateTimeImmutable;
use SeoSpider\Shared\Domain\DomainEvent;

final readonly class PageWasCrawled implements DomainEvent
{
    public function __construct(
        public string $pageId,
        public string $auditId,
        public string $url,
        public int $newUrlsDiscovered,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
