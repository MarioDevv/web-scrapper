<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Shared\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Shared\Domain\DomainEvent;
use SeoSpider\Shared\Integration\PageWasCrawled;

final class PageWasCrawledTest extends TestCase
{
    public function test_carries_only_primitives_so_consumers_do_not_need_to_import_crawling(): void
    {
        $occurredAt = new DateTimeImmutable('2026-05-20T10:00:00+00:00');

        $event = new PageWasCrawled(
            pageId: '019e4552-14b7-7434-b362-8140dc11b8e2',
            auditId: '019e4552-1500-7000-a000-000000000000',
            url: 'https://example.com/page',
            newUrlsDiscovered: 3,
            occurredAt: $occurredAt,
        );

        $this->assertSame('019e4552-14b7-7434-b362-8140dc11b8e2', $event->pageId);
        $this->assertSame('019e4552-1500-7000-a000-000000000000', $event->auditId);
        $this->assertSame('https://example.com/page', $event->url);
        $this->assertSame(3, $event->newUrlsDiscovered);
        $this->assertSame($occurredAt, $event->occurredAt());
    }

    public function test_implements_domain_event_interface(): void
    {
        $event = new PageWasCrawled(
            pageId: 'p',
            auditId: 'a',
            url: 'https://example.com/',
            newUrlsDiscovered: 0,
            occurredAt: new DateTimeImmutable(),
        );

        $this->assertInstanceOf(DomainEvent::class, $event);
    }
}
