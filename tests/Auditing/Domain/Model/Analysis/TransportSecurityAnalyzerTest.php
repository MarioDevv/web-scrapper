<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Auditing\Domain\Model\Analysis\TransportSecurityAnalyzer;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\Link;
use SeoSpider\Crawling\Domain\Model\Page\LinkRelation;
use SeoSpider\Crawling\Domain\Model\Page\LinkType;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Crawling\Domain\Model\Url;

final class TransportSecurityAnalyzerTest extends TestCase
{
    public function test_flags_pages_served_over_http(): void
    {
        $codes = $this->runOn($this->buildPage('http://example.com/'))->codes();

        $this->assertContains('http_insecure', $codes);
        $this->assertNotContains('mixed_content', $codes);
    }

    public function test_does_not_flag_pages_served_over_https(): void
    {
        $collector = $this->runOn($this->buildPage('https://example.com/'));

        $this->assertSame([], $collector->codes());
    }

    public function test_uses_final_url_when_redirect_landed_on_https(): void
    {
        $collector = $this->runOn($this->buildPage(
            url: 'http://example.com/',
            finalUrl: 'https://example.com/',
        ));

        $this->assertSame([], $collector->codes());
    }

    public function test_flags_mixed_content_resources_on_https_pages(): void
    {
        $collector = $this->runOn($this->buildPage('https://example.com/', links: [
            $this->resource('http://cdn.example.net/app.js', LinkType::SCRIPT),
            $this->resource('http://cdn.example.net/app.css', LinkType::STYLESHEET),
            $this->resource('http://cdn.example.net/hero.png', LinkType::IMAGE),
        ]));

        $this->assertSame(['mixed_content'], $collector->codes());

        $this->assertStringContainsString('3 resource(s)', $collector->issues()[0]->message());
    }

    public function test_ignores_https_resources_on_https_pages(): void
    {
        $collector = $this->runOn($this->buildPage('https://example.com/', links: [
            $this->resource('https://cdn.example.net/app.js', LinkType::SCRIPT),
            $this->resource('https://cdn.example.net/hero.png', LinkType::IMAGE),
        ]));

        $this->assertSame([], $collector->codes());
    }

    public function test_ignores_anchor_links_when_checking_mixed_content(): void
    {
        $collector = $this->runOn($this->buildPage('https://example.com/', links: [
            new Link(
                targetUrl: Url::fromString('http://other-site.test/page'),
                type: LinkType::ANCHOR,
                anchorText: 'click',
                relation: LinkRelation::FOLLOW,
                isInternal: false,
            ),
        ]));

        $this->assertSame([], $collector->codes());
    }

    public function test_does_not_check_mixed_content_on_http_pages(): void
    {
        $collector = $this->runOn($this->buildPage('http://example.com/', links: [
            $this->resource('http://cdn.example.net/app.js', LinkType::SCRIPT),
        ]));

        $this->assertSame(['http_insecure'], $collector->codes());
    }

    private function runOn(Page $page): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals($page);
        $collector = new InMemoryIssueCollector();

        (new TransportSecurityAnalyzer())->analyze($signals, $collector);

        return $collector;
    }

    /** @param Link[] $links */
    private function buildPage(string $url, ?string $finalUrl = null, array $links = []): Page
    {
        $requestUrl = Url::fromString($url);
        $response = new PageResponse(
            statusCode: new HttpStatusCode(200),
            headers: ['content-type' => 'text/html; charset=utf-8'],
            body: '<html><body>ok</body></html>',
            contentType: 'text/html; charset=utf-8',
            bodySize: 28,
            responseTime: 0.1,
            finalUrl: $finalUrl !== null ? Url::fromString($finalUrl) : $requestUrl,
        );

        $page = Page::fromCrawl(
            id: PageId::generate(),
            auditId: AuditId::generate(),
            url: $requestUrl,
            response: $response,
            redirectChain: RedirectChain::none(),
            crawlDepth: 0,
        );

        if ($links !== []) {
            $page->enrichWithLinks($links);
        }

        return $page;
    }

    private function resource(string $url, LinkType $type): Link
    {
        return new Link(
            targetUrl: Url::fromString($url),
            type: $type,
            anchorText: null,
            relation: LinkRelation::FOLLOW,
            isInternal: false,
        );
    }
}
