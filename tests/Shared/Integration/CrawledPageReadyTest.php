<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Shared\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Shared\Domain\DomainEvent;
use SeoSpider\Shared\Integration\CrawledPagePayload;
use SeoSpider\Shared\Integration\CrawledPageReady;

final class CrawledPageReadyTest extends TestCase
{
    public function test_is_a_domain_event_carrying_the_payload(): void
    {
        $payload = new CrawledPagePayload(
            auditId: '0190a000-0000-7000-8000-000000000000',
            url: 'https://example.com/',
            crawlDepth: 0,
            isHtml: true,
            isIndexable: true,
            statusCode: 200,
            contentType: 'text/html',
            bodySize: 10,
            responseTime: 0.1,
        );
        $occurredAt = new DateTimeImmutable('2026-05-19T10:00:00+00:00');

        $event = new CrawledPageReady($payload, $occurredAt);

        $this->assertInstanceOf(DomainEvent::class, $event);
        $this->assertSame($payload, $event->payload);
        $this->assertEquals($occurredAt, $event->occurredAt());
    }
}
