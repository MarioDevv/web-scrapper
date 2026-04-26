<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\GetAuditIssueReport;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Application\GetAuditIssueReport\GetAuditIssueReportHandler;
use SeoSpider\Audit\Application\GetAuditIssueReport\GetAuditIssueReportQuery;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryIssueReportReader;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;

final class GetAuditIssueReportHandlerTest extends TestCase
{
    private InMemoryAuditRepository $audits;
    private InMemoryPageRepository $pages;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->audits = new InMemoryAuditRepository();
        $this->pages = new InMemoryPageRepository();
        $events = new InMemoryEventBus();
        $start = new StartAuditHandler($this->audits, new InMemoryFrontier(new UrlCanonicalizer()), $events);
        $response = $start(new StartAuditCommand(seedUrl: 'https://example.com'));
        $this->auditId = new AuditId($response->auditId);
    }

    public function test_raises_audit_not_found_for_unknown_id(): void
    {
        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));

        $this->expectException(AuditNotFound::class);
        $handler(new GetAuditIssueReportQuery(AuditId::generate()->value()));
    }

    public function test_returns_empty_report_when_no_issues(): void
    {
        $this->persistPage('https://example.com/a', []);

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame(0, $report->totalIssues);
        $this->assertSame(0, $report->affectedPages);
        $this->assertSame([], $report->groups);
    }

    public function test_groups_issues_by_code_across_pages(): void
    {
        $this->persistPage('https://example.com/a', [
            $this->issue('title_missing', IssueSeverity::ERROR, IssueCategory::METADATA),
            $this->issue('content_thin', IssueSeverity::NOTICE, IssueCategory::CONTENT),
        ]);
        $this->persistPage('https://example.com/b', [
            $this->issue('title_missing', IssueSeverity::ERROR, IssueCategory::METADATA),
        ]);

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame(3, $report->totalIssues);
        $this->assertSame(2, $report->affectedPages);
        $this->assertSame(['error' => 2, 'notice' => 1], $report->severityTotals);
        $this->assertCount(2, $report->groups);

        // Errors sort before notices regardless of page count
        $this->assertSame('title_missing', $report->groups[0]->code);
        $this->assertSame('error', $report->groups[0]->severity);
        $this->assertSame(2, $report->groups[0]->affectedPageCount);
        $this->assertSame(2, $report->groups[0]->count);

        $this->assertSame('content_thin', $report->groups[1]->code);
        $this->assertSame('notice', $report->groups[1]->severity);
        $this->assertSame(1, $report->groups[1]->affectedPageCount);
    }

    public function test_enriches_groups_with_catalog_prose(): void
    {
        $this->persistPage('https://example.com/a', [
            $this->issue('title_missing', IssueSeverity::ERROR, IssueCategory::METADATA),
        ]);

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $group = $report->groups[0];
        $this->assertNotNull($group->title);
        $this->assertNotNull($group->why);
        $this->assertNotNull($group->how);
        $this->assertStringContainsString('title', strtolower($group->title));
    }

    public function test_dedupes_affected_pages_when_same_code_emitted_twice_on_one_page(): void
    {
        $this->persistPage('https://example.com/a', [
            $this->issue('hreflang_invalid_language', IssueSeverity::ERROR, IssueCategory::HREFLANG, context: 'xx'),
            $this->issue('hreflang_invalid_language', IssueSeverity::ERROR, IssueCategory::HREFLANG, context: 'yy'),
        ]);

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $group = $report->groups[0];
        $this->assertSame(2, $group->count, 'raw occurrences should be counted');
        $this->assertSame(1, $group->affectedPageCount, 'the same page should only appear once');
        $this->assertCount(1, $group->affectedPages);
    }

    public function test_site_score_is_100_when_audit_has_no_issues(): void
    {
        $this->persistPage('https://example.com/a', []);
        $this->persistPage('https://example.com/b', []);

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame(100, $report->siteScore);
    }

    public function test_site_score_is_100_when_audit_has_no_pages(): void
    {
        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame(100, $report->siteScore);
    }

    public function test_site_score_drops_with_an_error_issue(): void
    {
        // Single page with one ERROR (title_missing → weight 10).
        // page_score = 100 × (1 − 10/30) ≈ 67. Audit = avg = 67.
        $this->persistPage('https://example.com/a', [
            $this->issue('title_missing', IssueSeverity::ERROR, IssueCategory::METADATA),
        ]);

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame(67, $report->siteScore);
    }

    public function test_site_score_dilutes_with_audit_size(): void
    {
        // 1 ERROR on a single page out of 10 keeps the audit mostly clean.
        // page_a = 67, the other 9 score 100. Audit = (67 + 900) / 10 ≈ 97.
        $this->persistPage('https://example.com/a', [
            $this->issue('title_missing', IssueSeverity::ERROR, IssueCategory::METADATA),
        ]);
        for ($i = 1; $i < 10; $i++) {
            $this->persistPage(sprintf('https://example.com/p-%d', $i), []);
        }

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame(97, $report->siteScore);
    }

    public function test_site_score_clamps_a_single_overwhelmed_page_at_zero(): void
    {
        // 5 ERROR-weight-10 issues on a single page yield a raw page
        // weight of 50, clamped to MAX_PAGE_PENALTY=30 → page_score 0.
        // With only that page in the audit, audit = 0.
        $this->persistPage('https://example.com/a', [
            $this->issue('title_missing', IssueSeverity::ERROR, IssueCategory::METADATA),
            $this->issue('redirect_loop', IssueSeverity::ERROR, IssueCategory::LINKS),
            $this->issue('client_error', IssueSeverity::ERROR, IssueCategory::LINKS),
            $this->issue('server_error', IssueSeverity::ERROR, IssueCategory::LINKS),
            $this->issue('response_very_slow', IssueSeverity::ERROR, IssueCategory::PERFORMANCE),
        ]);

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame(0, $report->siteScore);
    }

    public function test_site_score_averages_across_pages_with_mixed_severities(): void
    {
        // Page A: 1 NOTICE weight 2 → page_score 100×(1-2/30) ≈ 93.
        // Page B: clean → 100.
        // Audit = (93 + 100)/2 ≈ 97.
        $this->persistPage('https://example.com/a', [
            $this->issue('content_thin', IssueSeverity::NOTICE, IssueCategory::CONTENT),
        ]);
        $this->persistPage('https://example.com/b', []);

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame(97, $report->siteScore);
    }

    public function test_groups_sort_by_weight_times_affected_pages(): void
    {
        // notice on 5 pages (weight 2 × 5 = 10) should outrank an
        // error on a single page (weight 10 × 1 = 10) only on tiebreak,
        // so we make notice clearly higher: notice on 6 pages = 12.
        $this->persistPage('https://example.com/x', [
            $this->issue('title_missing', IssueSeverity::ERROR, IssueCategory::METADATA),
        ]);
        for ($i = 0; $i < 6; $i++) {
            $this->persistPage(sprintf('https://example.com/n-%d', $i), [
                $this->issue('content_thin', IssueSeverity::NOTICE, IssueCategory::CONTENT),
            ]);
        }

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame('content_thin', $report->groups[0]->code, 'higher impact (weight × pages) wins over severity');
        $this->assertSame('title_missing', $report->groups[1]->code);
    }

    public function test_groups_carry_their_weight(): void
    {
        $this->persistPage('https://example.com/a', [
            $this->issue('title_missing', IssueSeverity::ERROR, IssueCategory::METADATA),
        ]);

        $handler = new GetAuditIssueReportHandler($this->audits, new InMemoryIssueReportReader($this->pages));
        $report = $handler(new GetAuditIssueReportQuery($this->auditId->value()));

        $this->assertSame(10, $report->groups[0]->weight);
    }

    /** @param Issue[] $issues */
    private function persistPage(string $url, array $issues): void
    {
        $page = Page::fromCrawl(
            id: $this->pages->nextId(),
            auditId: $this->auditId,
            url: Url::fromString($url),
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: '',
                contentType: 'text/html',
                bodySize: 0,
                responseTime: 10.0,
                finalUrl: null,
            ),
            redirectChain: null,
            crawlDepth: 0,
        );

        foreach ($issues as $issue) {
            $page->addIssue($issue);
        }

        $this->pages->save($page);
    }

    private function issue(string $code, IssueSeverity $severity, IssueCategory $category, ?string $context = null): Issue
    {
        return new Issue(
            id: IssueId::generate(),
            category: $category,
            severity: $severity,
            code: $code,
            message: "{$code} message",
            context: $context,
        );
    }
}
