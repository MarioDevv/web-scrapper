<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Page;
use SeoSpider\Crawling\Domain\Model\Page\Link;

use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\Directive;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Crawling\Domain\Model\Page\Page;
use SeoSpider\Crawling\Domain\Model\Page\PageCrawled;
use SeoSpider\Crawling\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Url;

final class PageTest extends TestCase
{
    private function createHtmlPage(int $statusCode = 200): Page
    {
        return Page::fromCrawl(
            id: PageId::generate(),
            auditId: AuditId::generate()->value(),
            url: Url::fromString('https://example.com/test'),
            response: new PageResponse(
                statusCode: new HttpStatusCode($statusCode),
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: '<html><head><title>Test</title></head><body>Hello</body></html>',
                contentType: 'text/html; charset=utf-8',
                bodySize: 62,
                responseTime: 150.0,
                finalUrl: null,
            ),
            redirectChain: null,
            crawlDepth: 1,
        );
    }

    public function test_from_crawl_creates_page_with_correct_state(): void
    {
        $url = Url::fromString('https://example.com/page');
        $auditId = AuditId::generate();

        $page = Page::fromCrawl(
            id: PageId::generate(),
            auditId: $auditId->value(),
            url: $url,
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: null,
                contentType: 'text/html',
                bodySize: 0,
                responseTime: 100.0,
                finalUrl: null,
            ),
            redirectChain: null,
            crawlDepth: 2,
        );

        $this->assertSame($auditId->value(), $page->auditId());
        $this->assertSame($url, $page->url());
        $this->assertSame(2, $page->crawlDepth());
        $this->assertTrue($page->redirectChain()->isEmpty());
        $this->assertSame([], $page->links());
        $this->assertSame([], $page->hreflangs());
        $this->assertNull($page->metadata());
        $this->assertNull($page->directives());
        $this->assertNull($page->fingerprint());
    }

    public function test_is_html_delegates_to_response(): void
    {
        $page = $this->createHtmlPage();

        $this->assertTrue($page->isHtml());
    }

    public function test_is_broken_for_4xx(): void
    {
        $page = $this->createHtmlPage(404);

        $this->assertTrue($page->isBroken());
    }

    public function test_is_broken_for_5xx(): void
    {
        $page = $this->createHtmlPage(500);

        $this->assertTrue($page->isBroken());
    }

    public function test_is_not_broken_for_200(): void
    {
        $page = $this->createHtmlPage(200);

        $this->assertFalse($page->isBroken());
    }

    public function test_is_redirect_for_301(): void
    {
        $page = $this->createHtmlPage(301);

        $this->assertTrue($page->isRedirect());
    }

    // ─── Indexability ──────────────────────────────────────────

    public function test_200_without_noindex_is_indexable(): void
    {
        $page = $this->createHtmlPage();
        $page->enrichWithDirectives(new Directive());

        $this->assertTrue($page->isIndexable());
    }

    public function test_200_with_noindex_is_not_indexable(): void
    {
        $page = $this->createHtmlPage();
        $page->enrichWithDirectives(new Directive(noindex: true));

        $this->assertFalse($page->isIndexable());
    }

    public function test_200_with_non_self_canonical_is_not_indexable(): void
    {
        $page = $this->createHtmlPage();
        $page->enrichWithDirectives(new Directive(
            canonical: Url::fromString('https://example.com/other-page'),
        ));

        $this->assertFalse($page->isIndexable());
    }

    public function test_200_with_self_canonical_is_indexable(): void
    {
        $page = $this->createHtmlPage();
        $page->enrichWithDirectives(new Directive(
            canonical: Url::fromString('https://example.com/test'),
        ));

        $this->assertTrue($page->isIndexable());
    }

    public function test_404_is_never_indexable(): void
    {
        $page = $this->createHtmlPage(404);

        $this->assertFalse($page->isIndexable());
    }

    // ─── Links ─────────────────────────────────────────────────

    public function test_internal_and_external_links_are_filtered(): void
    {
        $page = $this->createHtmlPage();

        $page->enrichWithLinks([
            new \SeoSpider\Crawling\Domain\Model\Page\Link(
                Url::fromString('https://example.com/about'),
                \SeoSpider\Crawling\Domain\Model\Page\LinkType::ANCHOR,
                'About', \SeoSpider\Crawling\Domain\Model\Page\LinkRelation::FOLLOW, true,
            ),
            new \SeoSpider\Crawling\Domain\Model\Page\Link(
                Url::fromString('https://external.com'),
                \SeoSpider\Crawling\Domain\Model\Page\LinkType::ANCHOR,
                'External', \SeoSpider\Crawling\Domain\Model\Page\LinkRelation::NOFOLLOW, false,
            ),
        ]);

        $this->assertCount(1, $page->internalLinks());
        $this->assertCount(1, $page->externalLinks());
    }
}
