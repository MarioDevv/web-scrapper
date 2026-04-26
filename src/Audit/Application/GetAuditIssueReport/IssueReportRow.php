<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

final readonly class IssueReportRow
{
    /**
     * @param ?string $pageId null when the issue is site-wide and has
     *                        no Page to attach to (e.g. sitemap orphan).
     */
    public function __construct(
        public ?string $pageId,
        public ?string $pageUrl,
        public string $code,
        public string $severity,
        public string $category,
        public ?string $context,
    ) {
    }
}
