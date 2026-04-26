<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\GetAuditPages;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesHandler;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;

final class GetAuditPagesHandlerTest extends TestCase
{
    private InMemoryAuditRepository $audits;
    private InMemoryPageRepository $pages;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->audits = new InMemoryAuditRepository();
        $this->pages = new InMemoryPageRepository();
        $start = new StartAuditHandler($this->audits, new InMemoryFrontier(new UrlCanonicalizer()), new InMemoryEventBus());
        $r = $start(new StartAuditCommand(seedUrl: 'https://example.com'));
        $this->auditId = new AuditId($r->auditId);
    }

    public function test_raises_audit_not_found_for_unknown_id(): void
    {
        $handler = new GetAuditPagesHandler($this->audits, $this->pages);

        $this->expectException(AuditNotFound::class);
        $handler(new GetAuditPagesQuery(AuditId::generate()->value()));
    }

    public function test_returns_full_audit_when_since_is_null(): void
    {
        $this->persistPage('https://example.com/a', '2026-04-26T10:00:00+00:00');
        $this->persistPage('https://example.com/b', '2026-04-26T10:00:05+00:00');

        $handler = new GetAuditPagesHandler($this->audits, $this->pages);
        $response = $handler(new GetAuditPagesQuery($this->auditId->value()));

        $this->assertCount(2, $response->pages);
        $this->assertSame(2, $response->total);
    }

    public function test_returns_only_pages_crawled_strictly_after_since(): void
    {
        $this->persistPage('https://example.com/a', '2026-04-26T10:00:00+00:00');
        $this->persistPage('https://example.com/b', '2026-04-26T10:00:05+00:00');
        $this->persistPage('https://example.com/c', '2026-04-26T10:00:10+00:00');

        $handler = new GetAuditPagesHandler($this->audits, $this->pages);
        $response = $handler(new GetAuditPagesQuery(
            auditId: $this->auditId->value(),
            since: '2026-04-26T10:00:05+00:00',
        ));

        $this->assertCount(1, $response->pages, 'only the page with crawledAt > since must be returned');
        $this->assertSame('https://example.com/c', $response->pages[0]->url);
        $this->assertSame(3, $response->total, 'total must reflect the whole audit, not the delta page count');
    }

    public function test_returns_empty_delta_when_since_matches_latest_page(): void
    {
        $this->persistPage('https://example.com/a', '2026-04-26T10:00:00+00:00');

        $handler = new GetAuditPagesHandler($this->audits, $this->pages);
        $response = $handler(new GetAuditPagesQuery(
            auditId: $this->auditId->value(),
            since: '2026-04-26T10:00:00+00:00',
        ));

        $this->assertSame([], $response->pages);
        $this->assertSame(1, $response->total);
    }

    private function persistPage(string $url, string $crawledAtIso): void
    {
        $page = Page::fromCrawl(
            id: $this->pages->nextId(),
            auditId: $this->auditId,
            url: Url::fromString($url),
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: '',
                contentType: 'text/html',
                bodySize: 0,
                responseTime: 0.1,
                finalUrl: null,
            ),
            redirectChain: null,
            crawlDepth: 0,
        );

        // Override the auto-generated crawledAt so tests can drive the
        // since boundary deterministically.
        $reflection = new ReflectionProperty(Page::class, 'crawledAt');
        $reflection->setValue($page, new DateTimeImmutable($crawledAtIso));

        $this->pages->save($page);
    }
}
