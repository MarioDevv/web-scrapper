<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\HeadingAnalyzer;

final class HeadingAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_no_issues_for_well_structured_outline(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(
            h1s: ['Page'],
            h2s: ['Section A', 'Section B'],
            headingHierarchy: [
                ['level' => 1, 'text' => 'Page'],
                ['level' => 2, 'text' => 'Section A'],
                ['level' => 3, 'text' => 'Detail'],
                ['level' => 2, 'text' => 'Section B'],
            ],
        ));

        (new HeadingAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_flags_h2_missing_when_only_h1_present(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(h1s: ['Page'], h2s: []));

        (new HeadingAnalyzer())->analyze($page);

        $this->assertContains('h2_missing', $this->codes($page));
    }

    public function test_flags_h1_not_first_when_h2_leads(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(
            h1s: ['Late Page'],
            h2s: ['Lead'],
            headingHierarchy: [
                ['level' => 2, 'text' => 'Lead'],
                ['level' => 1, 'text' => 'Late Page'],
            ],
        ));

        (new HeadingAnalyzer())->analyze($page);

        $this->assertContains('h1_not_first', $this->codes($page));
    }

    public function test_flags_heading_skip_h1_to_h3(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(
            h1s: ['Page'],
            h2s: [],
            headingHierarchy: [
                ['level' => 1, 'text' => 'Page'],
                ['level' => 3, 'text' => 'Skipped'],
            ],
        ));

        (new HeadingAnalyzer())->analyze($page);

        $this->assertContains('heading_skip', $this->codes($page));
    }

    public function test_skips_when_metadata_is_missing(): void
    {
        $page = $this->pageAt('https://example.com/');

        (new HeadingAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    /** @return string[] */
    private function codes(\SeoSpider\Audit\Domain\Model\Page\Page $page): array
    {
        return array_map(static fn($i) => $i->code(), $page->issues());
    }
}
