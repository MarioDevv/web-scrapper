<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\IssueCollector;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;

final class BufferingIssueCollector implements IssueCollector
{
    /** @var Issue[] */
    private array $issues = [];

    public function add(Issue $issue): void
    {
        $this->issues[] = $issue;
    }

    /** @return Issue[] */
    public function issues(): array
    {
        return $this->issues;
    }

    public function errorCount(): int
    {
        return count(array_filter($this->issues, static fn (Issue $i): bool => $i->isError()));
    }

    public function warningCount(): int
    {
        return count(array_filter($this->issues, static fn (Issue $i): bool => $i->isWarning()));
    }
}
