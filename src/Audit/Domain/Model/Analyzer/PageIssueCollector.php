<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Audit\Domain\Model\Page\Page;

final readonly class PageIssueCollector implements IssueCollector
{
    public function __construct(private Page $page)
    {
    }

    public function add(Issue $issue): void
    {
        $this->page->addIssue($issue);
    }
}
