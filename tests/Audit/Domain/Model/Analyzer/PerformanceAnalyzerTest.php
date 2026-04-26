<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\PerformanceAnalyzer;

final class PerformanceAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_no_issues_for_fast_small_page(): void
    {
        $page = $this->pageAt('https://example.com/', responseTime: 100.0, bodySize: 50_000);

        (new PerformanceAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_flags_slow_response(): void
    {
        $page = $this->pageAt('https://example.com/', responseTime: 1500.0);

        (new PerformanceAnalyzer())->analyze($page);

        $this->assertSame(['response_slow'], $this->codes($page));
    }

    public function test_flags_very_slow_response_instead_of_slow(): void
    {
        $page = $this->pageAt('https://example.com/', responseTime: 4000.0);

        (new PerformanceAnalyzer())->analyze($page);

        $this->assertSame(['response_very_slow'], $this->codes($page));
    }

    public function test_flags_oversized_html_page(): void
    {
        $page = $this->pageAt('https://example.com/', bodySize: 600 * 1024);

        (new PerformanceAnalyzer())->analyze($page);

        $this->assertContains('page_too_large', $this->codes($page));
    }

    public function test_does_not_flag_oversized_non_html(): void
    {
        $page = $this->pageAt(
            'https://example.com/binary',
            bodySize: 800 * 1024,
            contentType: 'application/octet-stream',
        );

        (new PerformanceAnalyzer())->analyze($page);

        $this->assertNotContains('page_too_large', $this->codes($page));
    }

    /** @return string[] */
    private function codes(\SeoSpider\Audit\Domain\Model\Page\Page $page): array
    {
        return array_map(static fn($i) => $i->code(), $page->issues());
    }
}
