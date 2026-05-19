<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\AuditedPage;

use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class AuditedPageTest extends TestCase
{
    private function issue(string $code, IssueSeverity $severity): Issue
    {
        return new Issue(
            id: IssueId::generate(),
            category: IssueCategory::CONTENT,
            severity: $severity,
            code: $code,
            message: 'x',
        );
    }

    public function test_perfect_page_scores_100(): void
    {
        $page = AuditedPage::forUrl('audit-1', 'https://example.com/');

        $this->assertSame(100, $page->score()->value());
        $this->assertSame([], $page->issues());
        $this->assertSame(0, $page->errorCount());
        $this->assertSame(0, $page->warningCount());
    }

    public function test_score_subtracts_rule_weight_per_issue(): void
    {
        $page = AuditedPage::forUrl('audit-1', 'https://example.com/');
        // unknown code -> falls back to severity default weight (error=10)
        $page->recordIssue($this->issue('made_up_error', IssueSeverity::ERROR));

        $this->assertSame(90, $page->score()->value());
        $this->assertSame(1, $page->errorCount());
    }

    public function test_issues_are_deduplicated_by_code(): void
    {
        $page = AuditedPage::forUrl('audit-1', 'https://example.com/');
        $page->recordIssue($this->issue('made_up_warning', IssueSeverity::WARNING));
        $page->recordIssue($this->issue('made_up_warning', IssueSeverity::WARNING));

        $this->assertCount(1, $page->issues());
        // warning default weight = 5 -> 100 - 5
        $this->assertSame(95, $page->score()->value());
    }

    public function test_score_never_below_zero(): void
    {
        $page = AuditedPage::forUrl('audit-1', 'https://example.com/');
        for ($i = 0; $i < 20; $i++) {
            $page->recordIssue($this->issue("err_$i", IssueSeverity::ERROR));
        }

        $this->assertSame(0, $page->score()->value());
    }
}
