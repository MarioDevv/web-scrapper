<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\RobotsIndexableAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\SiteAuditContext;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\StubRobotsPolicy;

final class RobotsIndexableAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_indexable_page_disallowed_by_robots(): void
    {
        $page = $this->pageAt('https://example.com/blocked');
        $robots = new StubRobotsPolicy();
        $robots->disallow('https://example.com/blocked');

        $this->runAnalyzer($robots, $page);

        $codes = array_map(static fn($i) => $i->code(), $page->issues());
        $this->assertSame(['robots_blocks_indexable'], $codes);
    }

    public function test_does_not_flag_noindex_page_blocked_by_robots(): void
    {
        // noindex pages are not indexable — robots.txt blocking them is
        // consistent, not conflicting.
        $page = $this->pageAt('https://example.com/blocked', noindex: true);
        $robots = new StubRobotsPolicy();
        $robots->disallow('https://example.com/blocked');

        $this->runAnalyzer($robots, $page);

        $this->assertSame([], $page->issues());
    }

    public function test_does_not_flag_indexable_page_not_blocked(): void
    {
        $page = $this->pageAt('https://example.com/');

        $this->runAnalyzer(new StubRobotsPolicy(), $page);

        $this->assertSame([], $page->issues());
    }

    public function test_does_not_flag_pages_returning_4xx(): void
    {
        // 4xx pages are not indexable per Page::isIndexable, so blocking
        // them in robots.txt is not a conflict.
        $page = $this->pageAt('https://example.com/missing', statusCode: 404);
        $robots = new StubRobotsPolicy();
        $robots->disallow('https://example.com/missing');

        $this->runAnalyzer($robots, $page);

        $this->assertSame([], $page->issues());
    }

    private function runAnalyzer(StubRobotsPolicy $robots, \SeoSpider\Audit\Domain\Model\Page\Page ...$pages): void
    {
        $context = new SiteAuditContext(
            auditId: $this->buildAuditId(),
            seedUrl: Url::fromString('https://example.com/'),
            pages: $pages,
        );

        (new RobotsIndexableAnalyzer($robots))->analyze($context);
    }
}
