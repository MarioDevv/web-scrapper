<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Shared\Integration;

use PHPUnit\Framework\TestCase;
use SeoSpider\Shared\Integration\CrawledPagePayload;

final class CrawledPagePayloadTest extends TestCase
{
    public function test_exposes_all_fields_as_primitives(): void
    {
        $payload = new CrawledPagePayload(
            auditId: '0190a000-0000-7000-8000-000000000000',
            url: 'https://example.com/',
            crawlDepth: 2,
            isHtml: true,
            isIndexable: false,
            statusCode: 200,
            contentType: 'text/html',
            bodySize: 1234,
            responseTime: 0.42,
        );

        $this->assertSame('0190a000-0000-7000-8000-000000000000', $payload->auditId);
        $this->assertSame('https://example.com/', $payload->url);
        $this->assertSame(2, $payload->crawlDepth);
        $this->assertTrue($payload->isHtml);
        $this->assertFalse($payload->isIndexable);
        $this->assertSame(200, $payload->statusCode);
        $this->assertSame('text/html', $payload->contentType);
        $this->assertSame(1234, $payload->bodySize);
        $this->assertSame(0.42, $payload->responseTime);
    }
}
