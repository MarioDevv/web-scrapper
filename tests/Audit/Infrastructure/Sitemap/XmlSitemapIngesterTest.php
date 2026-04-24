<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Sitemap;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\DiscoverySource;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Audit\Infrastructure\Sitemap\XmlSitemapIngester;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\StubHttpClient;

final class XmlSitemapIngesterTest extends TestCase
{
    private StubHttpClient $http;
    private InMemoryFrontier $frontier;
    private XmlSitemapIngester $ingester;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->http = new StubHttpClient();
        $this->frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $this->ingester = new XmlSitemapIngester($this->http, $this->frontier);
        $this->auditId = AuditId::generate();
    }

    public function test_reads_sitemap_urls_from_robots_txt_and_enqueues_them(): void
    {
        $this->respondWith('https://example.com/robots.txt', "User-agent: *\nSitemap: https://example.com/sitemap.xml\n");
        $this->respondWith('https://example.com/sitemap.xml', $this->urlset([
            'https://example.com/a',
            'https://example.com/b',
        ]));

        $added = $this->ingester->ingest($this->auditId, Url::fromString('https://example.com/'));

        $this->assertSame(2, $added);
        $this->assertTrue($this->frontier->isKnown($this->auditId, Url::fromString('https://example.com/a')));
        $this->assertTrue($this->frontier->isKnown($this->auditId, Url::fromString('https://example.com/b')));
        $this->assertSame(DiscoverySource::SITEMAP, $this->frontier->sourceOf(
            $this->auditId,
            Url::fromString('https://example.com/a'),
        ));
    }

    public function test_falls_back_to_well_known_sitemap_path_when_robots_has_no_sitemap_directive(): void
    {
        $this->respondWith('https://example.com/robots.txt', "User-agent: *\nDisallow: /admin\n");
        $this->respondWith('https://example.com/sitemap.xml', $this->urlset(['https://example.com/page']));

        $added = $this->ingester->ingest($this->auditId, Url::fromString('https://example.com/'));

        $this->assertSame(1, $added);
    }

    public function test_follows_a_sitemap_index_and_collects_urls_from_each_child(): void
    {
        $this->respondWith('https://example.com/robots.txt', "Sitemap: https://example.com/sitemap-index.xml\n");
        $this->respondWith('https://example.com/sitemap-index.xml', $this->sitemapIndex([
            'https://example.com/sitemap-1.xml',
            'https://example.com/sitemap-2.xml',
        ]));
        $this->respondWith('https://example.com/sitemap-1.xml', $this->urlset([
            'https://example.com/a',
            'https://example.com/b',
        ]));
        $this->respondWith('https://example.com/sitemap-2.xml', $this->urlset([
            'https://example.com/c',
        ]));

        $added = $this->ingester->ingest($this->auditId, Url::fromString('https://example.com/'));

        $this->assertSame(3, $added);
    }

    public function test_tracking_parameters_in_sitemap_urls_are_canonicalized_by_the_frontier(): void
    {
        $this->respondWith('https://example.com/robots.txt', "Sitemap: https://example.com/s.xml\n");
        $this->respondWith('https://example.com/s.xml', $this->urlset([
            'https://example.com/p?utm_source=sitemap',
            'https://example.com/p',
        ]));

        $added = $this->ingester->ingest($this->auditId, Url::fromString('https://example.com/'));

        $this->assertSame(1, $added, 'both entries should collapse into a single frontier row');
    }

    public function test_returns_zero_when_nothing_is_available(): void
    {
        $this->http->failWith('https://example.com/robots.txt', 'connection refused');
        $this->http->failWith('https://example.com/sitemap.xml', 'connection refused');

        $this->assertSame(0, $this->ingester->ingest($this->auditId, Url::fromString('https://example.com/')));
    }

    public function test_ignores_entries_with_malformed_loc_values(): void
    {
        $this->respondWith('https://example.com/robots.txt', "Sitemap: https://example.com/s.xml\n");
        $this->respondWith('https://example.com/s.xml', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url><loc>https://example.com/good</loc></url>
                <url><loc>   </loc></url>
                <url><loc>not-a-url</loc></url>
            </urlset>
            XML);

        $this->assertSame(1, $this->ingester->ingest($this->auditId, Url::fromString('https://example.com/')));
    }

    public function test_returns_zero_when_the_sitemap_response_is_not_valid_xml(): void
    {
        $this->respondWith('https://example.com/robots.txt', "Sitemap: https://example.com/s.xml\n");
        $this->respondWith('https://example.com/s.xml', '<html>not a sitemap</html>');

        $this->assertSame(0, $this->ingester->ingest($this->auditId, Url::fromString('https://example.com/')));
    }

    private function respondWith(string $url, string $body): void
    {
        $this->http->respondWith($url, new PageResponse(
            statusCode: new HttpStatusCode(200),
            headers: ['Content-Type' => 'application/xml'],
            body: $body,
            contentType: 'application/xml',
            bodySize: strlen($body),
            responseTime: 100.0,
            finalUrl: null,
        ));
    }

    /** @param string[] $locs */
    private function urlset(array $locs): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($locs as $loc) {
            $xml .= "  <url><loc>{$loc}</loc></url>\n";
        }
        $xml .= "</urlset>\n";

        return $xml;
    }

    /** @param string[] $locs */
    private function sitemapIndex(array $locs): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($locs as $loc) {
            $xml .= "  <sitemap><loc>{$loc}</loc></sitemap>\n";
        }
        $xml .= "</sitemapindex>\n";

        return $xml;
    }
}
