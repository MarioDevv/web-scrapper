<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\MetaDataAnalyzer;

final class MetaDataAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_no_issues_for_clean_metadata(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(
            title: 'A reasonably descriptive page title for tests',
            metaDescription: 'A meta description that is long enough to land in the recommended range.',
            h1s: ['Page'],
            viewport: 'width=device-width, initial-scale=1',
            lang: 'en',
        ));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_flags_title_missing(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(title: null));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertContains('title_missing', $this->codes($page));
    }

    public function test_flags_title_too_long(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(title: str_repeat('A', 80)));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertContains('title_too_long', $this->codes($page));
    }

    public function test_flags_title_too_short(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(title: 'short'));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertContains('title_too_short', $this->codes($page));
    }

    public function test_flags_meta_description_missing(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(metaDescription: null));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertContains('meta_description_missing', $this->codes($page));
    }

    public function test_flags_meta_description_too_long(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(
            metaDescription: str_repeat('A', 200),
        ));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertContains('meta_description_too_long', $this->codes($page));
    }

    public function test_flags_h1_missing(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(h1s: []));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertContains('h1_missing', $this->codes($page));
    }

    public function test_flags_h1_multiple(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(h1s: ['One', 'Two']));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertContains('h1_multiple', $this->codes($page));
    }

    public function test_flags_viewport_missing(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(viewport: null));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertContains('viewport_missing', $this->codes($page));
    }

    public function test_flags_html_lang_missing(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(lang: null));

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertContains('html_lang_missing', $this->codes($page));
    }

    public function test_skips_non_html_responses(): void
    {
        $page = $this->pageAt('https://example.com/file.pdf', contentType: 'application/pdf');

        (new MetaDataAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    /** @return string[] */
    private function codes(\SeoSpider\Audit\Domain\Model\Page\Page $page): array
    {
        return array_map(static fn($i) => $i->code(), $page->issues());
    }
}
