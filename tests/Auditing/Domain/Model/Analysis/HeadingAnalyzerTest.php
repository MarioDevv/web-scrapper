<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\HeadingAnalyzer;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class HeadingAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_no_issues_for_well_structured_outline(): void
    {
        $collector = $this->runOn($this->metadata(
            h1s: ['Page'],
            h2s: ['Section A', 'Section B'],
            headingHierarchy: [
                ['level' => 1, 'text' => 'Page'],
                ['level' => 2, 'text' => 'Section A'],
                ['level' => 3, 'text' => 'Detail'],
                ['level' => 2, 'text' => 'Section B'],
            ],
        ));

        $this->assertSame([], $collector->codes());
    }

    public function test_flags_h2_missing_when_only_h1_present(): void
    {
        $collector = $this->runOn($this->metadata(h1s: ['Page'], h2s: []));

        $this->assertContains('h2_missing', $collector->codes());
    }

    public function test_flags_h1_not_first_when_h2_leads(): void
    {
        $collector = $this->runOn($this->metadata(
            h1s: ['Late Page'],
            h2s: ['Lead'],
            headingHierarchy: [
                ['level' => 2, 'text' => 'Lead'],
                ['level' => 1, 'text' => 'Late Page'],
            ],
        ));

        $this->assertContains('h1_not_first', $collector->codes());
    }

    public function test_flags_heading_skip_h1_to_h3(): void
    {
        $collector = $this->runOn($this->metadata(
            h1s: ['Page'],
            h2s: [],
            headingHierarchy: [
                ['level' => 1, 'text' => 'Page'],
                ['level' => 3, 'text' => 'Skipped'],
            ],
        ));

        $this->assertContains('heading_skip', $collector->codes());
    }

    public function test_skips_when_metadata_is_missing(): void
    {
        $signals = new LegacyPageToPageSignals($this->pageAt('https://example.com/'));
        $collector = new InMemoryIssueCollector();

        (new HeadingAnalyzer())->analyze($signals, $collector);

        $this->assertSame([], $collector->codes());
    }

    private function runOn(\SeoSpider\Crawling\Domain\Model\Page\PageMetadata $metadata): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals(
            $this->pageAt('https://example.com/', metadata: $metadata),
        );
        $collector = new InMemoryIssueCollector();

        (new HeadingAnalyzer())->analyze($signals, $collector);

        return $collector;
    }
}
