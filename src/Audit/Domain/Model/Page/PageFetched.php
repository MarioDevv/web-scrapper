<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use DateTimeImmutable;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Shared\Domain\DomainEvent;

/**
 * Fired the moment a page has been fetched, enriched from HTML and persisted
 * for the first time — before any analyzer has run. Reactors subscribed to
 * this event own the subsequent analysis phase, which lets the crawl hot path
 * stay focused on "bring bytes in, save them, announce" while analyzers
 * compose independently and can also be re-triggered without re-crawling.
 */
final readonly class PageFetched implements DomainEvent
{
    public function __construct(
        public PageId $pageId,
        public AuditId $auditId,
        public int $newUrlsDiscovered,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
