<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use DateTimeImmutable;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Shared\Domain\DomainEvent;

final readonly class UrlDiscovered implements DomainEvent
{
    public function __construct(
        public AuditId $auditId,
        public Url $url,
        public Url $foundOnPage,
        public int $depth,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
