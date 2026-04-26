<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

/**
 * Issue that does not belong to any single Page — emitted by site-wide
 * analyzers about state that lives in the audit graph as a whole. The
 * canonical use case is a sitemap orphan: a URL declared in the
 * sitemap that the crawler never reached, so there is no Page to hang
 * the regular Issue off.
 */
final readonly class SiteIssue
{
    public function __construct(
        public IssueId $id,
        public IssueCategory $category,
        public IssueSeverity $severity,
        public string $code,
        public string $message,
        public ?string $context = null,
    ) {
    }
}
