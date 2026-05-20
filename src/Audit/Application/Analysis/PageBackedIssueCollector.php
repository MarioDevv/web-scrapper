<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\Analysis;

use SeoSpider\Audit\Domain\Model\Page\Page as LegacyPage;
use SeoSpider\Auditing\Domain\Model\Analysis\IssueCollector;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;

final readonly class PageBackedIssueCollector implements IssueCollector
{
    public function __construct(private LegacyPage $page)
    {
    }

    public function add(Issue $issue): void
    {
        $this->page->addIssue($issue);
    }
}
