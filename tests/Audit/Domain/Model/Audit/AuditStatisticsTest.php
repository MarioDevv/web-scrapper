<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Audit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatistics;

final class AuditStatisticsTest extends TestCase
{
    public function test_default_values_are_zero(): void
    {
        $stats = new AuditStatistics();

        $this->assertSame(0, $stats->pagesDiscovered);
        $this->assertSame(0, $stats->pagesCrawled);
        $this->assertSame(0, $stats->pagesFailed);
        $this->assertSame(0, $stats->issuesFound);
        $this->assertSame(0, $stats->errorsFound);
        $this->assertSame(0, $stats->warningsFound);
        $this->assertNull($stats->startedAt);
        $this->assertNull($stats->completedAt);
    }

    public function test_with_page_crawled_is_immutable(): void
    {
        $original = new AuditStatistics();

        $updated = $original->withPageCrawled();

        $this->assertSame(0, $original->pagesCrawled);
        $this->assertSame(1, $updated->pagesCrawled);
        $this->assertNotSame($original, $updated);
    }

    public function test_with_page_crawled_only_changes_crawled_count(): void
    {
        $stats = new AuditStatistics(
            pagesDiscovered: 10,
            pagesFailed: 2,
            issuesFound: 5,
        );

        $updated = $stats->withPageCrawled();

        $this->assertSame(10, $updated->pagesDiscovered);
        $this->assertSame(1, $updated->pagesCrawled);
        $this->assertSame(2, $updated->pagesFailed);
        $this->assertSame(5, $updated->issuesFound);
    }

    public function test_with_page_failed_increments_failed(): void
    {
        $stats = new AuditStatistics(pagesFailed: 3);

        $updated = $stats->withPageFailed();

        $this->assertSame(4, $updated->pagesFailed);
    }

    public function test_with_urls_discovered_adds_count(): void
    {
        $stats = new AuditStatistics(pagesDiscovered: 5);

        $updated = $stats->withUrlsDiscovered(10);

        $this->assertSame(15, $updated->pagesDiscovered);
    }

    public function test_with_issues_accumulates_correctly(): void
    {
        $stats = new AuditStatistics();

        $updated = $stats
            ->withIssues(errors: 2, warnings: 3)
            ->withIssues(errors: 1, warnings: 0);

        $this->assertSame(6, $updated->issuesFound);
        $this->assertSame(3, $updated->errorsFound);
        $this->assertSame(3, $updated->warningsFound);
    }

    public function test_is_limit_reached(): void
    {
        $stats = new AuditStatistics(pagesCrawled: 500);

        $this->assertTrue($stats->isLimitReached(500));
        $this->assertTrue($stats->isLimitReached(499));
        $this->assertFalse($stats->isLimitReached(501));
    }

    public function test_duration_returns_null_when_not_completed(): void
    {
        $stats = new AuditStatistics(startedAt: new DateTimeImmutable());

        $this->assertNull($stats->duration());
    }

    public function test_duration_calculates_seconds(): void
    {
        $start = new DateTimeImmutable('2026-01-01 10:00:00');
        $end = new DateTimeImmutable('2026-01-01 10:05:30');

        $stats = new AuditStatistics(startedAt: $start, completedAt: $end);

        $this->assertSame(330.0, $stats->duration());
    }
}
