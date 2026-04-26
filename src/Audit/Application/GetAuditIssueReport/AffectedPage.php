<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

final readonly class AffectedPage
{
    /**
     * @param ?string $pageId null when the row represents a site-wide
     *                        issue (e.g. sitemap orphan) where no Page
     *                        was crawled and the URL comes from context.
     */
    public function __construct(
        public ?string $pageId,
        public string $url,
        public ?string $context,
    ) {
    }
}
