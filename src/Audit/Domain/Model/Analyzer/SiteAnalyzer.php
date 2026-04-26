<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\IssueCategory;

/**
 * Analyzer that operates on the entire audit graph (every crawled Page)
 * rather than a single Page in isolation. Site analyzers run once after
 * the crawl finishes and can reach across the page set to detect
 * cross-page conditions like hreflang reciprocity or canonical chains
 * landing on broken targets.
 */
interface SiteAnalyzer
{
    public function analyze(SiteAuditContext $context): void;

    public function category(): IssueCategory;
}
