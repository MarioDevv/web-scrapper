<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Crawling\Application\RobotsPolicy;

final class RobotsIndexableAnalyzer implements SiteAnalyzer
{
    public function __construct(private readonly RobotsPolicy $robotsPolicy)
    {
    }

    public function analyze(SiteAuditContext $context): void
    {
        // The reactor primes the policy for the seed before invoking the
        // analyzer; loading again here is cheap (cached) and makes the
        // analyzer safe to invoke in isolation from tests.
        $this->robotsPolicy->load($context->seedUrl);

        foreach ($context->pages as $page) {
            if (!$page->isIndexable()) {
                continue;
            }

            if ($this->robotsPolicy->isAllowed($page->url())) {
                continue;
            }

            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::WARNING,
                code: 'robots_blocks_indexable',
                message: 'Page is indexable (no noindex) but robots.txt disallows crawling it. Conflicting signals.',
                context: $page->url()->toString(),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::DIRECTIVES;
    }
}
