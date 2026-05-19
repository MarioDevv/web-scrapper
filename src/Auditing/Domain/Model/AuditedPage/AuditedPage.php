<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\AuditedPage;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueRule;
use SeoSpider\Auditing\Domain\Model\Issue\IssueRuleCatalog;

/**
 * The Core aggregate root: one web page as the audit dictamines it —
 * its findings and the resulting health score. References its Audit by
 * id only. Issues are findings keyed by code (purely read-only
 * occurrences; recording the same code twice is idempotent), and the
 * score is always recomputed from them so `score == f(issues, weights)`
 * holds at all times. That invariant is the aggregate boundary; it is
 * why Issue is a Value Object inside AuditedPage, not its own AR.
 *
 * Built by the Auditing ACL from crawl signals (3c-3); never imports
 * Crawling.
 */
final class AuditedPage
{
    /** @var array<string, Issue> keyed by issue code */
    private array $issues = [];

    private function __construct(
        private readonly string $auditId,
        private readonly string $url,
    ) {
    }

    public static function forUrl(string $auditId, string $url): self
    {
        return new self($auditId, $url);
    }

    public function recordIssue(Issue $issue): void
    {
        $this->issues[$issue->code()] = $issue;
    }

    public function auditId(): string
    {
        return $this->auditId;
    }

    public function url(): string
    {
        return $this->url;
    }

    /** @return Issue[] */
    public function issues(): array
    {
        return array_values($this->issues);
    }

    public function score(): AuditScore
    {
        $penalty = 0;
        foreach ($this->issues as $issue) {
            $rule = IssueRuleCatalog::forCode($issue->code());
            $penalty += $rule?->weight()
                ?? IssueRule::defaultWeightFor($issue->severity());
        }

        return AuditScore::fromPenalty($penalty);
    }

    public function errorCount(): int
    {
        return count(array_filter(
            $this->issues,
            static fn (Issue $i): bool => $i->isError(),
        ));
    }

    public function warningCount(): int
    {
        return count(array_filter(
            $this->issues,
            static fn (Issue $i): bool => $i->isWarning(),
        ));
    }
}
