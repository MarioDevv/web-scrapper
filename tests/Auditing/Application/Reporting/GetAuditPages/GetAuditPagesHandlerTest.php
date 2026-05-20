<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Application\Reporting\GetAuditPages;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SeoSpider\Auditing\Application\AuditNotFound;
use SeoSpider\Auditing\Application\Reporting\GetAuditPages\GetAuditPagesHandler;
use SeoSpider\Auditing\Application\Reporting\GetAuditPages\GetAuditPagesQuery;
use SeoSpider\Auditing\Application\Lifecycle\StartAudit\StartAuditCommand;
use SeoSpider\Auditing\Application\Lifecycle\StartAudit\StartAuditHandler;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Url;
use SeoSpider\Crawling\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Audit\Application\Analysis\FrontierBackedAuditFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageSummaryReader;

final class GetAuditPagesHandlerTest extends TestCase
{
    private InMemoryAuditRepository $audits;
    private InMemoryPageRepository $pages;
    private \SeoSpider\Tests\Auditing\Infrastructure\InMemory\InMemoryAuditedPageRepository $auditedPages;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->audits = new InMemoryAuditRepository();
        $this->pages = new InMemoryPageRepository();
        $this->auditedPages = new \SeoSpider\Tests\Auditing\Infrastructure\InMemory\InMemoryAuditedPageRepository();
        $start = new StartAuditHandler($this->audits, new FrontierBackedAuditFrontier(new InMemoryFrontier(new UrlCanonicalizer())), new InMemoryEventBus());
        $auditId = AuditId::generate()->value();
        $start(new StartAuditCommand(auditId: $auditId, seedUrl: 'https://example.com'));
        $this->auditId = new AuditId($auditId);
    }

    public function test_raises_audit_not_found_for_unknown_id(): void
    {
        $handler = new GetAuditPagesHandler($this->audits, new InMemoryPageSummaryReader($this->pages, $this->auditedPages));

        $this->expectException(AuditNotFound::class);
        $handler(new GetAuditPagesQuery(AuditId::generate()->value()));
    }

    public function test_returns_full_audit_when_since_is_null(): void
    {
        $this->persistPage('https://example.com/a', '2026-04-26T10:00:00+00:00');
        $this->persistPage('https://example.com/b', '2026-04-26T10:00:05+00:00');

        $handler = new GetAuditPagesHandler($this->audits, new InMemoryPageSummaryReader($this->pages, $this->auditedPages));
        $response = $handler(new GetAuditPagesQuery($this->auditId->value()));

        $this->assertCount(2, $response->pages);
        $this->assertSame(2, $response->total);
    }

    public function test_returns_only_pages_crawled_strictly_after_since(): void
    {
        $this->persistPage('https://example.com/a', '2026-04-26T10:00:00+00:00');
        $this->persistPage('https://example.com/b', '2026-04-26T10:00:05+00:00');
        $this->persistPage('https://example.com/c', '2026-04-26T10:00:10+00:00');

        $handler = new GetAuditPagesHandler($this->audits, new InMemoryPageSummaryReader($this->pages, $this->auditedPages));
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

        $handler = new GetAuditPagesHandler($this->audits, new InMemoryPageSummaryReader($this->pages, $this->auditedPages));
        $response = $handler(new GetAuditPagesQuery(
            auditId: $this->auditId->value(),
            since: '2026-04-26T10:00:00+00:00',
        ));

        $this->assertSame([], $response->pages);
        $this->assertSame(1, $response->total);
    }

    public function test_filters_by_search_term_against_url(): void
    {
        $this->persistPage('https://example.com/blog', '2026-04-26T10:00:00+00:00');
        $this->persistPage('https://example.com/about', '2026-04-26T10:00:01+00:00');

        $handler = new GetAuditPagesHandler($this->audits, new InMemoryPageSummaryReader($this->pages, $this->auditedPages));
        $response = $handler(new GetAuditPagesQuery(
            auditId: $this->auditId->value(),
            search: 'blog',
        ));

        $this->assertCount(1, $response->pages);
        $this->assertStringContainsString('blog', $response->pages[0]->url);
        $this->assertSame(1, $response->total);
    }

    public function test_paginates_with_limit_offset_while_total_tracks_full_match(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->persistPage(
                "https://example.com/p{$i}",
                sprintf('2026-04-26T10:00:0%d+00:00', $i),
            );
        }

        $handler = new GetAuditPagesHandler($this->audits, new InMemoryPageSummaryReader($this->pages, $this->auditedPages));

        $first = $handler(new GetAuditPagesQuery(
            auditId: $this->auditId->value(),
            sortField: 'crawledAt',
            sortDir: 'asc',
            limit: 2,
            offset: 0,
        ));
        $this->assertCount(2, $first->pages);
        $this->assertSame(5, $first->total);

        $second = $handler(new GetAuditPagesQuery(
            auditId: $this->auditId->value(),
            sortField: 'crawledAt',
            sortDir: 'asc',
            limit: 2,
            offset: 2,
        ));
        $this->assertCount(2, $second->pages);
        $this->assertNotSame(
            $first->pages[0]->pageId,
            $second->pages[0]->pageId,
            'second page must contain different rows than the first',
        );
    }

    public function test_sort_descending_reverses_order(): void
    {
        $this->persistPage('https://example.com/a', '2026-04-26T10:00:00+00:00');
        $this->persistPage('https://example.com/b', '2026-04-26T10:00:05+00:00');

        $handler = new GetAuditPagesHandler($this->audits, new InMemoryPageSummaryReader($this->pages, $this->auditedPages));
        $response = $handler(new GetAuditPagesQuery(
            auditId: $this->auditId->value(),
            sortField: 'crawledAt',
            sortDir: 'desc',
        ));

        $this->assertSame('https://example.com/b', $response->pages[0]->url);
        $this->assertSame('https://example.com/a', $response->pages[1]->url);
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
