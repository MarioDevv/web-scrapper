<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacySiteContext;
use SeoSpider\Crawling\Domain\Model\Page\Page;
use SeoSpider\Auditing\Domain\Model\Analysis\SitemapCoverageAnalyzer;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class SitemapCoverageAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_does_not_flag_when_no_sitemap_was_ingested(): void
    {
        $page = $this->pageAt('https://example.com/');

        $context = $this->runAnalyzer(new InMemorySitemapIndex(), $page);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_does_not_flag_pages_present_in_sitemap(): void
    {
        $page = $this->pageAt('https://example.com/blog/post-1');
        $sitemap = new InMemorySitemapIndex();
        $sitemap->put($this->buildAuditId()->value(), 'https://example.com/blog/post-1');

        $context = $this->runAnalyzer($sitemap, $page);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_flags_pages_missing_from_sitemap(): void
    {
        $page = $this->pageAt('https://example.com/orphan');
        $sitemap = new InMemorySitemapIndex();
        $sitemap->put($this->buildAuditId()->value(), 'https://example.com/blog/post-1');

        $context = $this->runAnalyzer($sitemap, $page);

        $issues = $context->bufferedPageIssues()[$page->url()->toString()] ?? [];
        $codes = array_map(static fn ($i) => $i->code(), $issues);
        $this->assertSame(['sitemap_missing'], $codes);
    }

    public function test_does_not_flag_non_indexable_pages(): void
    {
        $page = $this->pageAt('https://example.com/admin', noindex: true);
        $sitemap = new InMemorySitemapIndex();
        $sitemap->put($this->buildAuditId()->value(), 'https://example.com/');

        $context = $this->runAnalyzer($sitemap, $page);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_does_not_flag_pages_returning_4xx(): void
    {
        $page = $this->pageAt('https://example.com/gone', statusCode: 404);
        $sitemap = new InMemorySitemapIndex();
        $sitemap->put($this->buildAuditId()->value(), 'https://example.com/');

        $context = $this->runAnalyzer($sitemap, $page);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_matches_via_final_url_after_redirect(): void
    {
        $page = $this->pageAt(
            url: 'https://example.com/old',
            finalUrl: 'https://example.com/new',
        );
        $sitemap = new InMemorySitemapIndex();
        $sitemap->put($this->buildAuditId()->value(), 'https://example.com/new');

        $context = $this->runAnalyzer($sitemap, $page);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_emits_sitemap_orphan_for_uncrawled_sitemap_url(): void
    {
        $crawled = $this->pageAt('https://example.com/');
        $sitemap = new InMemorySitemapIndex();
        $auditId = $this->buildAuditId()->value();
        $sitemap->put($auditId, 'https://example.com/');
        $sitemap->put($auditId, 'https://example.com/orphan');

        $context = $this->runAnalyzer($sitemap, $crawled);

        $codes = array_map(static fn ($i) => $i->code, $context->siteIssues());
        $this->assertSame(['sitemap_orphans'], $codes);
        $this->assertStringContainsString('orphan', $context->siteIssues()[0]->context ?? '');
    }

    public function test_does_not_emit_orphans_when_every_sitemap_url_is_crawled(): void
    {
        $a = $this->pageAt('https://example.com/');
        $b = $this->pageAt('https://example.com/blog');
        $sitemap = new InMemorySitemapIndex();
        $auditId = $this->buildAuditId()->value();
        foreach (['https://example.com/', 'https://example.com/blog'] as $url) {
            $sitemap->put($auditId, $url);
        }

        $context = $this->runAnalyzer($sitemap, $a, $b);

        $this->assertSame([], $context->siteIssues());
    }

    private function runAnalyzer(InMemorySitemapIndex $sitemap, Page ...$pages): LegacySiteContext
    {
        $context = new LegacySiteContext(
            auditId: $this->buildAuditId()->value(),
            seedUrl: 'https://example.com/',
            pages: $pages,
        );

        (new SitemapCoverageAnalyzer($sitemap))->analyze($context);

        return $context;
    }
}
