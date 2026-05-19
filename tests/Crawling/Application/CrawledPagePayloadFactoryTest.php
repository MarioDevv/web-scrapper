<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Crawling\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Crawling\Application\CrawledPagePayloadFactory;

final class CrawledPagePayloadFactoryTest extends TestCase
{
    public function test_maps_page_aggregate_to_flat_payload(): void
    {
        $auditId = AuditId::generate();
        $page = Page::reconstitute(
            id: PageId::generate(),
            auditId: $auditId,
            url: Url::fromString('https://example.com/about'),
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: '<html><head><title>x</title></head><body>hi</body></html>',
                contentType: 'text/html',
                bodySize: 57,
                responseTime: 0.25,
                finalUrl: null,
            ),
            redirectChain: RedirectChain::none(),
            crawlDepth: 3,
            metadata: null,
            directives: null,
            fingerprint: null,
            links: [],
            hreflangs: [],
            issues: [],
            crawledAt: new DateTimeImmutable('2026-05-19T10:00:00+00:00'),
        );

        $payload = (new CrawledPagePayloadFactory())->fromPage($page);

        $this->assertSame($auditId->value(), $payload->auditId);
        $this->assertSame('https://example.com/about', $payload->url);
        $this->assertSame(3, $payload->crawlDepth);
        $this->assertSame(200, $payload->statusCode);
        $this->assertSame('text/html', $payload->contentType);
        $this->assertSame(57, $payload->bodySize);
        $this->assertSame(0.25, $payload->responseTime);
        $this->assertIsBool($payload->isHtml);
        $this->assertIsBool($payload->isIndexable);
    }
}
