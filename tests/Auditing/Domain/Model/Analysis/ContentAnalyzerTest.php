<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\ContentAnalyzer;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class ContentAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_does_not_flag_normal_content(): void
    {
        $signals = new LegacyPageToPageSignals(
            $this->pageAt('https://example.com/', metadata: $this->metadata(wordCount: 200)),
        );
        $collector = new InMemoryIssueCollector();

        (new ContentAnalyzer())->analyze($signals, $collector);

        $this->assertSame([], $collector->codes());
    }

    public function test_flags_empty_content(): void
    {
        $signals = new LegacyPageToPageSignals(
            $this->pageAt('https://example.com/', metadata: $this->metadata(wordCount: 10)),
        );
        $collector = new InMemoryIssueCollector();

        (new ContentAnalyzer())->analyze($signals, $collector);

        $this->assertSame(['content_empty'], $collector->codes());
    }

    public function test_flags_thin_content(): void
    {
        $signals = new LegacyPageToPageSignals(
            $this->pageAt('https://example.com/', metadata: $this->metadata(wordCount: 60)),
        );
        $collector = new InMemoryIssueCollector();

        (new ContentAnalyzer())->analyze($signals, $collector);

        $this->assertSame(['content_thin'], $collector->codes());
    }

    public function test_skips_non_html_responses(): void
    {
        $signals = new LegacyPageToPageSignals(
            $this->pageAt('https://example.com/file.pdf', contentType: 'application/pdf'),
        );
        $collector = new InMemoryIssueCollector();

        (new ContentAnalyzer())->analyze($signals, $collector);

        $this->assertSame([], $collector->codes());
    }

    public function test_skips_failed_responses(): void
    {
        $signals = new LegacyPageToPageSignals(
            $this->pageAt('https://example.com/missing', statusCode: 404, metadata: $this->metadata(wordCount: 5)),
        );
        $collector = new InMemoryIssueCollector();

        (new ContentAnalyzer())->analyze($signals, $collector);

        $this->assertSame([], $collector->codes());
    }
}
