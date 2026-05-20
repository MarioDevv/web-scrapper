<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacySiteContext;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Auditing\Domain\Model\Analysis\RobotsIndexableAnalyzer;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class RobotsIndexableAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_indexable_page_disallowed_by_robots(): void
    {
        $page = $this->pageAt('https://example.com/blocked');
        $robots = new StubRobotsCheck();
        $robots->disallow('https://example.com/blocked');

        $context = $this->runAnalyzer($robots, $page);

        $issues = $context->bufferedPageIssues()[$page->url()->toString()] ?? [];
        $codes = array_map(static fn ($i) => $i->code(), $issues);
        $this->assertSame(['robots_blocks_indexable'], $codes);
    }

    public function test_does_not_flag_noindex_page_blocked_by_robots(): void
    {
        $page = $this->pageAt('https://example.com/blocked', noindex: true);
        $robots = new StubRobotsCheck();
        $robots->disallow('https://example.com/blocked');

        $context = $this->runAnalyzer($robots, $page);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_does_not_flag_indexable_page_not_blocked(): void
    {
        $page = $this->pageAt('https://example.com/');

        $context = $this->runAnalyzer(new StubRobotsCheck(), $page);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_does_not_flag_pages_returning_4xx(): void
    {
        $page = $this->pageAt('https://example.com/missing', statusCode: 404);
        $robots = new StubRobotsCheck();
        $robots->disallow('https://example.com/missing');

        $context = $this->runAnalyzer($robots, $page);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    private function runAnalyzer(StubRobotsCheck $robots, Page ...$pages): LegacySiteContext
    {
        $context = new LegacySiteContext(
            auditId: $this->buildAuditId()->value(),
            seedUrl: 'https://example.com/',
            pages: $pages,
        );

        (new RobotsIndexableAnalyzer($robots))->analyze($context);

        return $context;
    }
}
