<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final readonly class RobotsIndexableAnalyzer implements SiteAnalyzer
{
    public function __construct(private RobotsCheck $robots)
    {
    }

    public function analyze(SiteContext $context): void
    {
        $this->robots->load($context->seedUrl());

        foreach ($context->pages() as $page) {
            if (!$page->isIndexable()) {
                continue;
            }
            if ($this->robots->isAllowed($page->url())) {
                continue;
            }

            $context->addPageIssue($page->url(), new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::WARNING,
                code: 'robots_blocks_indexable',
                message: 'Page is indexable (no noindex) but robots.txt disallows crawling it. Conflicting signals.',
                context: $page->url(),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::DIRECTIVES;
    }
}
