<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\ContentAnalyzer;

final class ContentAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_does_not_flag_normal_content(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(wordCount: 200));

        (new ContentAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_flags_empty_content(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(wordCount: 10));

        (new ContentAnalyzer())->analyze($page);

        $this->assertSame(['content_empty'], $this->codes($page));
    }

    public function test_flags_thin_content(): void
    {
        $page = $this->pageAt('https://example.com/', metadata: $this->metadata(wordCount: 60));

        (new ContentAnalyzer())->analyze($page);

        $this->assertSame(['content_thin'], $this->codes($page));
    }

    public function test_skips_non_html_responses(): void
    {
        $page = $this->pageAt('https://example.com/file.pdf', contentType: 'application/pdf');

        (new ContentAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_skips_failed_responses(): void
    {
        $page = $this->pageAt('https://example.com/missing', statusCode: 404, metadata: $this->metadata(wordCount: 5));

        (new ContentAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    /** @return string[] */
    private function codes(\SeoSpider\Audit\Domain\Model\Page\Page $page): array
    {
        return array_map(static fn($i) => $i->code(), $page->issues());
    }
}
