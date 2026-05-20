<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Infrastructure\Acl\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\HreflangAnalyzer;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class HreflangAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_no_issues_for_well_formed_self_referential_cluster(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('en', 'https://example.com/en/'),
            $this->hreflang('x-default', 'https://example.com/'),
        ]));

        $this->assertSame([], $collector->codes());
    }

    public function test_flags_invalid_language_code(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('xx', 'https://example.com/xx/'),
        ]));

        $this->assertContains('hreflang_invalid_language', $collector->codes());
    }

    public function test_flags_invalid_region_code(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('en-XYZ', 'https://example.com/en/'),
        ]));

        $this->assertContains('hreflang_invalid_region', $collector->codes());
    }

    public function test_flags_missing_self_reference(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/es/', hreflangs: [
            $this->hreflang('en', 'https://example.com/en/'),
        ]));

        $this->assertContains('hreflang_missing_self', $collector->codes());
    }

    public function test_flags_duplicate_language_region(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('es', 'https://example.com/duplicate/'),
        ]));

        $this->assertContains('hreflang_duplicate', $collector->codes());
    }

    public function test_skips_when_no_hreflangs_declared(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/'));

        $this->assertSame([], $collector->codes());
    }

    private function runOn(\SeoSpider\Crawling\Domain\Model\Page\Page $page): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals($page);
        $collector = new InMemoryIssueCollector();

        (new HreflangAnalyzer())->analyze($signals, $collector);

        return $collector;
    }
}
