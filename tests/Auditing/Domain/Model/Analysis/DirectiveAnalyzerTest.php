<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Infrastructure\Acl\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\DirectiveAnalyzer;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class DirectiveAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_canonical_missing_when_directive_block_lacks_canonical(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', noindex: true));

        $this->assertContains('canonical_missing', $collector->codes());
    }

    public function test_flags_noindex(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', noindex: true));

        $this->assertContains('noindex', $collector->codes());
    }

    public function test_flags_nofollow(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', nofollow: true));

        $this->assertContains('nofollow', $collector->codes());
    }

    public function test_does_not_flag_self_canonical(): void
    {
        $codes = $this->runOn(
            $this->pageAt('https://example.com/', canonical: 'https://example.com/'),
        )->codes();

        $this->assertNotContains('canonical_non_self', $codes);
        $this->assertNotContains('canonical_missing', $codes);
    }

    public function test_flags_canonical_non_self(): void
    {
        $collector = $this->runOn(
            $this->pageAt('https://example.com/duplicate/', canonical: 'https://example.com/main/'),
        );

        $this->assertContains('canonical_non_self', $collector->codes());
    }

    public function test_flags_noindex_with_canonical_conflict(): void
    {
        $collector = $this->runOn($this->pageAt(
            'https://example.com/page',
            canonical: 'https://example.com/main',
            noindex: true,
        ));

        $this->assertContains('noindex_with_canonical', $collector->codes());
    }

    public function test_skips_pages_without_directives(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/'));

        $this->assertSame([], $collector->codes());
    }

    private function runOn(\SeoSpider\Crawling\Domain\Model\Page\Page $page): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals($page);
        $collector = new InMemoryIssueCollector();

        (new DirectiveAnalyzer())->analyze($signals, $collector);

        return $collector;
    }
}
