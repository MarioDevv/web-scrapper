<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\IssueCollector;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;

final class InMemoryIssueCollector implements IssueCollector
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

    /** @return string[] */
    public function codes(): array
    {
        return array_map(static fn (Issue $i): string => $i->code(), $this->issues);
    }
}
