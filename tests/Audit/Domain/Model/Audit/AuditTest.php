<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Audit;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\Audit;
use SeoSpider\Audit\Domain\Model\Audit\AuditCancelled;
use SeoSpider\Audit\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Audit\Domain\Model\Audit\AuditConfiguration;
use SeoSpider\Audit\Domain\Model\Audit\AuditFailed;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditPaused;
use SeoSpider\Audit\Domain\Model\Audit\AuditResumed;
use SeoSpider\Audit\Domain\Model\Audit\AuditStarted;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatus;
use SeoSpider\Audit\Domain\Model\Audit\InvalidAuditStateTransition;
use SeoSpider\Audit\Domain\Model\Url;

final class AuditTest extends TestCase
{
    private function createAudit(int $maxPages = 500): Audit
    {
        return Audit::start(
            AuditId::generate(),
            new AuditConfiguration(
                seedUrl: Url::fromString('https://example.com'),
                maxPages: $maxPages,
            ),
        );
    }

    public function test_start_creates_running_audit(): void
    {
        $audit = $this->createAudit();

        $this->assertSame(AuditStatus::RUNNING, $audit->status());
        $this->assertTrue($audit->isRunning());
        $this->assertFalse($audit->isFinished());
    }

    public function test_start_records_audit_started_event(): void
    {
        $audit = $this->createAudit();

        $events = $audit->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(AuditStarted::class, $events[0]);
    }

    public function test_start_initializes_statistics_with_zero_counts(): void
    {
        $audit = $this->createAudit();

        $stats = $audit->statistics();
        $this->assertSame(0, $stats->pagesCrawled);
        $this->assertSame(0, $stats->pagesDiscovered);
        $this->assertSame(0, $stats->pagesFailed);
        $this->assertSame(0, $stats->issuesFound);
        $this->assertNotNull($stats->startedAt);
    }

    public function test_register_page_crawled_increments_statistics(): void
    {
        $audit = $this->createAudit();
        $audit->pullDomainEvents();

        $audit->registerPageCrawled(issueErrors: 1, issueWarnings: 2);

        $stats = $audit->statistics();
        $this->assertSame(1, $stats->pagesCrawled);
        $this->assertSame(3, $stats->issuesFound);
        $this->assertSame(1, $stats->errorsFound);
        $this->assertSame(2, $stats->warningsFound);
    }

    public function test_register_page_failed_increments_failed_count(): void
    {
        $audit = $this->createAudit();

        $audit->registerPageFailed();

        $this->assertSame(1, $audit->statistics()->pagesFailed);
    }

    public function test_register_urls_discovered_increments_discovered_count(): void
    {
        $audit = $this->createAudit();

        $audit->registerUrlsDiscovered(15);

        $this->assertSame(15, $audit->statistics()->pagesDiscovered);
    }

    public function test_auto_completes_when_limit_reached(): void
    {
        $audit = $this->createAudit(maxPages: 2);
        $audit->pullDomainEvents();

        $audit->registerPageCrawled(0, 0);
        $audit->registerPageCrawled(0, 0);

        $this->assertSame(AuditStatus::COMPLETED, $audit->status());
        $this->assertTrue($audit->isFinished());

        $events = $audit->pullDomainEvents();
        $this->assertInstanceOf(AuditCompleted::class, array_last($events));
    }

    public function test_can_accept_more_pages_while_under_limit(): void
    {
        $audit = $this->createAudit(maxPages: 10);

        $audit->registerPageCrawled(0, 0);

        $this->assertTrue($audit->canAcceptMorePages());
    }

    public function test_cannot_accept_more_pages_when_completed(): void
    {
        $audit = $this->createAudit(maxPages: 1);
        $audit->registerPageCrawled(0, 0);

        $this->assertFalse($audit->canAcceptMorePages());
    }

    // ─── Pause / Resume ────────────────────────────────────────

    public function test_pause_transitions_to_paused(): void
    {
        $audit = $this->createAudit();
        $audit->pullDomainEvents();

        $audit->pause();

        $this->assertSame(AuditStatus::PAUSED, $audit->status());
        $events = $audit->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(AuditPaused::class, $events[0]);
    }

    public function test_resume_transitions_back_to_running(): void
    {
        $audit = $this->createAudit();
        $audit->pause();
        $audit->pullDomainEvents();

        $audit->resume();

        $this->assertSame(AuditStatus::RUNNING, $audit->status());
        $events = $audit->pullDomainEvents();
        $this->assertInstanceOf(AuditResumed::class, $events[0]);
    }

    public function test_cannot_pause_a_completed_audit(): void
    {
        $audit = $this->createAudit(maxPages: 1);
        $audit->registerPageCrawled(0, 0);

        $this->expectException(InvalidAuditStateTransition::class);
        $audit->pause();
    }

    public function test_cannot_resume_a_running_audit(): void
    {
        $audit = $this->createAudit();

        $this->expectException(InvalidAuditStateTransition::class);
        $audit->resume();
    }

    // ─── Cancel ────────────────────────────────────────────────

    public function test_cancel_from_running(): void
    {
        $audit = $this->createAudit();
        $audit->pullDomainEvents();

        $audit->cancel();

        $this->assertSame(AuditStatus::CANCELLED, $audit->status());
        $this->assertTrue($audit->isFinished());
        $events = $audit->pullDomainEvents();
        $this->assertInstanceOf(AuditCancelled::class, $events[0]);
    }

    public function test_cancel_from_paused(): void
    {
        $audit = $this->createAudit();
        $audit->pause();
        $audit->pullDomainEvents();

        $audit->cancel();

        $this->assertSame(AuditStatus::CANCELLED, $audit->status());
    }

    public function test_cannot_cancel_a_completed_audit(): void
    {
        $audit = $this->createAudit(maxPages: 1);
        $audit->registerPageCrawled(0, 0);

        $this->expectException(InvalidAuditStateTransition::class);
        $audit->cancel();
    }

    // ─── Fail ──────────────────────────────────────────────────

    public function test_fail_transitions_to_failed(): void
    {
        $audit = $this->createAudit();
        $audit->pullDomainEvents();

        $audit->fail('Network unreachable');

        $this->assertSame(AuditStatus::FAILED, $audit->status());
        $this->assertTrue($audit->isFinished());
        $events = $audit->pullDomainEvents();
        $this->assertInstanceOf(AuditFailed::class, $events[0]);
        $this->assertSame('Network unreachable', $events[0]->reason);
    }

    // ─── Cannot operate on finished audits ─────────────────────

    public function test_cannot_crawl_pages_on_completed_audit(): void
    {
        $audit = $this->createAudit(maxPages: 1);
        $audit->registerPageCrawled(0, 0);

        $this->expectException(InvalidAuditStateTransition::class);
        $audit->registerPageCrawled(0, 0);
    }

    public function test_cannot_crawl_pages_on_paused_audit(): void
    {
        $audit = $this->createAudit();
        $audit->pause();

        $this->expectException(InvalidAuditStateTransition::class);
        $audit->registerPageCrawled(0, 0);
    }
}
