<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\TransportSecurityAnalyzer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\LinkRelation;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;

final class TransportSecurityAnalyzerTest extends TestCase
{
    public function test_flags_pages_served_over_http(): void
    {
        $page = $this->pageAt('http://example.com/');

        (new TransportSecurityAnalyzer())->analyze($page);

        $codes = $this->codes($page);
        $this->assertContains('http_insecure', $codes);
        $this->assertNotContains('mixed_content', $codes);
    }

    public function test_does_not_flag_pages_served_over_https(): void
    {
        $page = $this->pageAt('https://example.com/');

        (new TransportSecurityAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_uses_final_url_when_redirect_landed_on_https(): void
    {
        $page = $this->pageAt(
            url: 'http://example.com/',
            finalUrl: 'https://example.com/',
        );

        (new TransportSecurityAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_flags_mixed_content_resources_on_https_pages(): void
    {
        $page = $this->pageAt('https://example.com/', links: [
            $this->resource('http://cdn.example.net/app.js', LinkType::SCRIPT),
            $this->resource('http://cdn.example.net/app.css', LinkType::STYLESHEET),
            $this->resource('http://cdn.example.net/hero.png', LinkType::IMAGE),
        ]);

        (new TransportSecurityAnalyzer())->analyze($page);

        $codes = $this->codes($page);
        $this->assertSame(['mixed_content'], $codes);

        $issue = $page->issues()[0];
        $this->assertStringContainsString('3 resource(s)', $issue->message());
    }

    public function test_ignores_https_resources_on_https_pages(): void
    {
        $page = $this->pageAt('https://example.com/', links: [
            $this->resource('https://cdn.example.net/app.js', LinkType::SCRIPT),
            $this->resource('https://cdn.example.net/hero.png', LinkType::IMAGE),
        ]);

        (new TransportSecurityAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_ignores_anchor_links_when_checking_mixed_content(): void
    {
        $page = $this->pageAt('https://example.com/', links: [
            new Link(
                targetUrl: Url::fromString('http://other-site.test/page'),
                type: LinkType::ANCHOR,
                anchorText: 'click',
                relation: LinkRelation::FOLLOW,
                isInternal: false,
            ),
        ]);

        (new TransportSecurityAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_does_not_check_mixed_content_on_http_pages(): void
    {
        $page = $this->pageAt('http://example.com/', links: [
            $this->resource('http://cdn.example.net/app.js', LinkType::SCRIPT),
        ]);

        (new TransportSecurityAnalyzer())->analyze($page);

        $this->assertSame(['http_insecure'], $this->codes($page));
    }

    /** @param Link[] $links */
    private function pageAt(string $url, ?string $finalUrl = null, array $links = []): Page
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

    /** @return string[] */
    private function codes(Page $page): array
    {
        return array_map(static fn($issue) => $issue->code(), $page->issues());
    }
}
