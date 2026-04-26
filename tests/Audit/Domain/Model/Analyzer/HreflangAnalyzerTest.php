<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\HreflangAnalyzer;

final class HreflangAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_no_issues_for_well_formed_self_referential_cluster(): void
    {
        $page = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('en', 'https://example.com/en/'),
            $this->hreflang('x-default', 'https://example.com/'),
        ]);

        (new HreflangAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_flags_invalid_language_code(): void
    {
        $page = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('xx', 'https://example.com/xx/'),
        ]);

        (new HreflangAnalyzer())->analyze($page);

        $this->assertContains('hreflang_invalid_language', $this->codes($page));
    }

    public function test_flags_invalid_region_code(): void
    {
        $page = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('en-XYZ', 'https://example.com/en/'),
        ]);

        (new HreflangAnalyzer())->analyze($page);

        $this->assertContains('hreflang_invalid_region', $this->codes($page));
    }

    public function test_flags_missing_self_reference(): void
    {
        $page = $this->pageAt('https://example.com/es/', hreflangs: [
            $this->hreflang('en', 'https://example.com/en/'),
        ]);

        (new HreflangAnalyzer())->analyze($page);

        $this->assertContains('hreflang_missing_self', $this->codes($page));
    }

    public function test_flags_duplicate_language_region(): void
    {
        $page = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('es', 'https://example.com/duplicate/'),
        ]);

        (new HreflangAnalyzer())->analyze($page);

        $this->assertContains('hreflang_duplicate', $this->codes($page));
    }

    public function test_skips_when_no_hreflangs_declared(): void
    {
        $page = $this->pageAt('https://example.com/');

        (new HreflangAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    /** @return string[] */
    private function codes(\SeoSpider\Audit\Domain\Model\Page\Page $page): array
    {
        return array_map(static fn($i) => $i->code(), $page->issues());
    }
}
