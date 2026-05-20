<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\AuditedPage;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueRule;
use SeoSpider\Auditing\Domain\Model\Issue\IssueRuleCatalog;

final class AuditedPage
{
    /** @var array<string, Issue> */
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
