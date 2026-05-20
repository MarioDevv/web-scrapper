<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\PerformanceAnalyzer;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class PerformanceAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_no_issues_for_fast_small_page(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', responseTime: 100.0, bodySize: 50_000));

        $this->assertSame([], $collector->codes());
    }

    public function test_flags_slow_response(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', responseTime: 1500.0));

        $this->assertSame(['response_slow'], $collector->codes());
    }

    public function test_flags_very_slow_response_instead_of_slow(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', responseTime: 4000.0));

        $this->assertSame(['response_very_slow'], $collector->codes());
    }

    public function test_flags_oversized_html_page(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', bodySize: 600 * 1024));

        $this->assertContains('page_too_large', $collector->codes());
    }

    public function test_does_not_flag_oversized_non_html(): void
    {
        $collector = $this->runOn($this->pageAt(
            'https://example.com/binary',
            bodySize: 800 * 1024,
            contentType: 'application/octet-stream',
        ));

        $this->assertNotContains('page_too_large', $collector->codes());
    }

    private function runOn(\SeoSpider\Audit\Domain\Model\Page\Page $page): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals($page);
        $collector = new InMemoryIssueCollector();

        (new PerformanceAnalyzer())->analyze($signals, $collector);

        return $collector;
    }
}
