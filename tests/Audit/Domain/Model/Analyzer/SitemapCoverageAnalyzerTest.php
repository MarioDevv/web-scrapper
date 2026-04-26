<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\SiteAuditContext;
use SeoSpider\Audit\Domain\Model\Analyzer\SitemapCoverageAnalyzer;
use SeoSpider\Audit\Domain\Model\DiscoverySource;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;

final class SitemapCoverageAnalyzerTest extends TestCase
{
    use SiteAnalyzerTestHelpers;

    public function test_does_not_flag_when_no_sitemap_was_ingested(): void
    {
        $page = $this->pageAt('https://example.com/');
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());

        $this->runAnalyzer($frontier, $page);

        $this->assertSame([], $page->issues());
    }

    public function test_does_not_flag_pages_present_in_sitemap(): void
    {
        $page = $this->pageAt('https://example.com/blog/post-1');
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $frontier->enqueue(
            $this->buildAuditId(),
            Url::fromString('https://example.com/blog/post-1'),
            depth: 0,
            source: DiscoverySource::SITEMAP,
        );

        $this->runAnalyzer($frontier, $page);

        $this->assertSame([], $page->issues());
    }

    public function test_flags_pages_missing_from_sitemap(): void
    {
        $page = $this->pageAt('https://example.com/orphan');
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $frontier->enqueue(
            $this->buildAuditId(),
            Url::fromString('https://example.com/blog/post-1'),
            depth: 0,
            source: DiscoverySource::SITEMAP,
        );

        $this->runAnalyzer($frontier, $page);

        $codes = array_map(static fn($i) => $i->code(), $page->issues());
        $this->assertSame(['sitemap_missing'], $codes);
    }

    public function test_does_not_flag_non_indexable_pages(): void
    {
        $page = $this->pageAt('https://example.com/admin', noindex: true);
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $frontier->enqueue(
            $this->buildAuditId(),
            Url::fromString('https://example.com/'),
            depth: 0,
            source: DiscoverySource::SITEMAP,
        );

        $this->runAnalyzer($frontier, $page);

        $this->assertSame([], $page->issues());
    }

    public function test_does_not_flag_pages_returning_4xx(): void
    {
        $page = $this->pageAt('https://example.com/gone', statusCode: 404);
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $frontier->enqueue(
            $this->buildAuditId(),
            Url::fromString('https://example.com/'),
            depth: 0,
            source: DiscoverySource::SITEMAP,
        );

        $this->runAnalyzer($frontier, $page);

        $this->assertSame([], $page->issues());
    }

    public function test_matches_via_final_url_after_redirect(): void
    {
        $page = $this->pageAt(
            url: 'https://example.com/old',
            finalUrl: 'https://example.com/new',
        );
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $frontier->enqueue(
            $this->buildAuditId(),
            Url::fromString('https://example.com/new'),
            depth: 0,
            source: DiscoverySource::SITEMAP,
        );

        $this->runAnalyzer($frontier, $page);

        $this->assertSame([], $page->issues());
    }

    private function runAnalyzer(InMemoryFrontier $frontier, \SeoSpider\Audit\Domain\Model\Page\Page ...$pages): void
    {
        $context = new SiteAuditContext(
            auditId: $this->buildAuditId(),
            seedUrl: Url::fromString('https://example.com/'),
            pages: $pages,
        );

        (new SitemapCoverageAnalyzer($frontier))->analyze($context);
    }
}
