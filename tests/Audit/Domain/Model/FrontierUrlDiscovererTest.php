<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditConfiguration;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\FrontierUrlDiscoverer;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\LinkRelation;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;

final class FrontierUrlDiscovererTest extends TestCase
{
    private InMemoryFrontier $frontier;
    private FrontierUrlDiscoverer $discoverer;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $this->discoverer = new FrontierUrlDiscoverer($this->frontier);
        $this->auditId = AuditId::generate();
    }

    public function test_enqueues_followable_internal_anchor_links(): void
    {
        $page = $this->pageWithLinks([
            $this->anchor('https://example.com/a', follow: true),
            $this->anchor('https://example.com/b', follow: true),
        ]);

        $count = $this->discoverer->discoverFrom($page, $this->auditId, 0, $this->config());

        $this->assertSame(2, $count);
    }

    public function test_skips_nofollow_anchors(): void
    {
        $page = $this->pageWithLinks([
            $this->anchor('https://example.com/a', follow: false),
        ]);

        $this->assertSame(0, $this->discoverer->discoverFrom($page, $this->auditId, 0, $this->config()));
    }

    public function test_skips_resources_unless_crawl_resources_is_enabled(): void
    {
        $page = $this->pageWithLinks([
            $this->resource('https://example.com/style.css'),
        ]);

        $this->assertSame(0, $this->discoverer->discoverFrom(
            $page,
            $this->auditId,
            0,
            $this->config(crawlResources: false),
        ));

        $this->assertSame(1, $this->discoverer->discoverFrom(
            $page,
            $this->auditId,
            0,
            $this->config(crawlResources: true),
        ));
    }

    public function test_stops_at_max_depth(): void
    {
        $page = $this->pageWithLinks([
            $this->anchor('https://example.com/a', follow: true),
        ]);

        // currentDepth = maxDepth means nextDepth exceeds the limit.
        $count = $this->discoverer->discoverFrom($page, $this->auditId, 3, $this->config(maxDepth: 3));

        $this->assertSame(0, $count);
    }

    public function test_does_not_count_duplicates_that_the_frontier_dedupes(): void
    {
        $page = $this->pageWithLinks([
            $this->anchor('https://example.com/same', follow: true),
            $this->anchor('https://example.com/same?utm_source=x', follow: true),
        ]);

        // The second URL canonicalizes to the same frontier row as the first.
        $this->assertSame(1, $this->discoverer->discoverFrom($page, $this->auditId, 0, $this->config()));
    }

    /** @param Link[] $links */
    private function pageWithLinks(array $links): Page
    {
        $page = Page::fromCrawl(
            id: \SeoSpider\Audit\Domain\Model\Page\PageId::generate(),
            auditId: $this->auditId,
            url: Url::fromString('https://example.com/'),
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: '',
                contentType: 'text/html',
                bodySize: 0,
                responseTime: 0.0,
                finalUrl: null,
            ),
            redirectChain: null,
            crawlDepth: 0,
        );
        $page->enrichWithLinks($links);

        return $page;
    }

    private function anchor(string $url, bool $follow): Link
    {
        return new Link(
            targetUrl: Url::fromString($url),
            type: LinkType::ANCHOR,
            anchorText: null,
            relation: $follow ? LinkRelation::FOLLOW : LinkRelation::NOFOLLOW,
            isInternal: true,
        );
    }

    private function resource(string $url): Link
    {
        return new Link(
            targetUrl: Url::fromString($url),
            type: LinkType::STYLESHEET,
            anchorText: null,
            relation: LinkRelation::FOLLOW,
            isInternal: true,
        );
    }

    private function config(int $maxDepth = 10, bool $crawlResources = false): AuditConfiguration
    {
        return new AuditConfiguration(
            seedUrl: Url::fromString('https://example.com/'),
            maxDepth: $maxDepth,
            crawlResources: $crawlResources,
        );
    }
}
