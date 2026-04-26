<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Page;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueRule;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;

final class IssueRuleTest extends TestCase
{
    public function test_default_weight_for_error_is_10(): void
    {
        $this->assertSame(10, $this->rule(IssueSeverity::ERROR)->weight());
    }

    public function test_default_weight_for_warning_is_5(): void
    {
        $this->assertSame(5, $this->rule(IssueSeverity::WARNING)->weight());
    }

    public function test_default_weight_for_notice_is_2(): void
    {
        $this->assertSame(2, $this->rule(IssueSeverity::NOTICE)->weight());
    }

    public function test_default_weight_for_info_is_0(): void
    {
        $this->assertSame(0, $this->rule(IssueSeverity::INFO)->weight());
    }

    public function test_override_takes_precedence_over_default(): void
    {
        $this->assertSame(3, $this->rule(IssueSeverity::ERROR, weightOverride: 3)->weight());
        $this->assertSame(8, $this->rule(IssueSeverity::INFO, weightOverride: 8)->weight());
    }

    public function test_zero_override_is_distinguishable_from_default(): void
    {
        // Default for ERROR is 10. Setting override=0 must yield 0, not 10.
        $this->assertSame(0, $this->rule(IssueSeverity::ERROR, weightOverride: 0)->weight());
    }

    private function rule(IssueSeverity $severity, ?int $weightOverride = null): IssueRule
    {
        return new IssueRule(
            code: 'test_code',
            category: IssueCategory::METADATA,
            severity: $severity,
            title: 'Title',
            summary: 'Summary',
            why: 'Why',
            how: 'How',
            weightOverride: $weightOverride,
        );
    }
}
