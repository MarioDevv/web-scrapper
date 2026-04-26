<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\DirectiveAnalyzer;

final class DirectiveAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_canonical_missing_when_directive_block_lacks_canonical(): void
    {
        // The Directive aggregate has to be enriched for the canonical
        // check to run; using noindex=true is the smallest enrichment
        // that still leaves canonical empty.
        $page = $this->pageAt('https://example.com/', noindex: true);

        (new DirectiveAnalyzer())->analyze($page);

        $this->assertContains('canonical_missing', $this->codes($page));
    }

    public function test_flags_noindex(): void
    {
        $page = $this->pageAt('https://example.com/', noindex: true);

        (new DirectiveAnalyzer())->analyze($page);

        $this->assertContains('noindex', $this->codes($page));
    }

    public function test_flags_nofollow(): void
    {
        $page = $this->pageAt('https://example.com/', nofollow: true);

        (new DirectiveAnalyzer())->analyze($page);

        $this->assertContains('nofollow', $this->codes($page));
    }

    public function test_does_not_flag_self_canonical(): void
    {
        $page = $this->pageAt('https://example.com/', canonical: 'https://example.com/');

        (new DirectiveAnalyzer())->analyze($page);

        $codes = $this->codes($page);
        $this->assertNotContains('canonical_non_self', $codes);
        $this->assertNotContains('canonical_missing', $codes);
    }

    public function test_flags_canonical_non_self(): void
    {
        $page = $this->pageAt('https://example.com/duplicate/', canonical: 'https://example.com/main/');

        (new DirectiveAnalyzer())->analyze($page);

        $this->assertContains('canonical_non_self', $this->codes($page));
    }

    public function test_flags_noindex_with_canonical_conflict(): void
    {
        $page = $this->pageAt(
            'https://example.com/page',
            canonical: 'https://example.com/main',
            noindex: true,
        );

        (new DirectiveAnalyzer())->analyze($page);

        $this->assertContains('noindex_with_canonical', $this->codes($page));
    }

    public function test_skips_pages_without_directives(): void
    {
        $page = $this->pageAt('https://example.com/');

        (new DirectiveAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    /** @return string[] */
    private function codes(\SeoSpider\Audit\Domain\Model\Page\Page $page): array
    {
        return array_map(static fn($i) => $i->code(), $page->issues());
    }
}
