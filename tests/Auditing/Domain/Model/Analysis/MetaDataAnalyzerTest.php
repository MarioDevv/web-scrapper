<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Infrastructure\Acl\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\MetaDataAnalyzer;
use SeoSpider\Crawling\Domain\Model\Page\PageMetadata as CrawlingPageMetadata;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class MetaDataAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_no_issues_for_clean_metadata(): void
    {
        $collector = $this->runOn($this->metadata(
            title: 'A reasonably descriptive page title for tests',
            metaDescription: 'A meta description that is long enough to land in the recommended range.',
            h1s: ['Page'],
            viewport: 'width=device-width, initial-scale=1',
            lang: 'en',
        ));

        $this->assertSame([], $collector->codes());
    }

    public function test_flags_title_missing(): void
    {
        $this->assertContains('title_missing', $this->runOn($this->metadata(title: null))->codes());
    }

    public function test_flags_title_too_long(): void
    {
        $this->assertContains('title_too_long', $this->runOn($this->metadata(title: str_repeat('A', 80)))->codes());
    }

    public function test_flags_title_too_short(): void
    {
        $this->assertContains('title_too_short', $this->runOn($this->metadata(title: 'short'))->codes());
    }

    public function test_flags_meta_description_missing(): void
    {
        $this->assertContains('meta_description_missing', $this->runOn($this->metadata(metaDescription: null))->codes());
    }

    public function test_flags_meta_description_too_long(): void
    {
        $this->assertContains(
            'meta_description_too_long',
            $this->runOn($this->metadata(metaDescription: str_repeat('A', 200)))->codes(),
        );
    }

    public function test_flags_h1_missing(): void
    {
        $this->assertContains('h1_missing', $this->runOn($this->metadata(h1s: []))->codes());
    }

    public function test_flags_h1_multiple(): void
    {
        $this->assertContains('h1_multiple', $this->runOn($this->metadata(h1s: ['One', 'Two']))->codes());
    }

    public function test_flags_viewport_missing(): void
    {
        $this->assertContains('viewport_missing', $this->runOn($this->metadata(viewport: null))->codes());
    }

    public function test_flags_html_lang_missing(): void
    {
        $this->assertContains('html_lang_missing', $this->runOn($this->metadata(lang: null))->codes());
    }

    public function test_skips_non_html_responses(): void
    {
        $signals = new LegacyPageToPageSignals(
            $this->pageAt('https://example.com/file.pdf', contentType: 'application/pdf'),
        );
        $collector = new InMemoryIssueCollector();

        (new MetaDataAnalyzer())->analyze($signals, $collector);

        $this->assertSame([], $collector->codes());
    }

    private function runOn(CrawlingPageMetadata $metadata): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals(
            $this->pageAt('https://example.com/', metadata: $metadata),
        );
        $collector = new InMemoryIssueCollector();

        (new MetaDataAnalyzer())->analyze($signals, $collector);

        return $collector;
    }
}
