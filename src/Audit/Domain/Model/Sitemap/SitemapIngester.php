<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Sitemap;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Url;

/**
 * Seeds the frontier with URLs declared in the site's sitemap(s). Essential
 * for SPAs and any site where the link graph is incomplete, and the source
 * of truth for the "orphan pages" and "undeclared pages" reports.
 */
interface SitemapIngester
{
    /**
     * Discovers and parses the sitemap(s) for the given seed, enqueues every
     * found URL into the frontier as SITEMAP-sourced at depth 0, and returns
     * the number of URLs newly added.
     */
    public function ingest(AuditId $auditId, Url $seedUrl, ?string $userAgent = null): int;
}
