<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\Page;

/**
 * Behaviour-preserving collector: forwards every emitted Issue to the
 * legacy Page aggregate exactly as analyzers did before the seam. Lets
 * the analyzer pipeline stay byte-for-byte equivalent during 3a.
 */
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
