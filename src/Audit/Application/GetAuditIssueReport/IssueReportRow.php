<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

final readonly class IssueReportRow
{
    public function __construct(
        public string $pageId,
        public string $pageUrl,
        public string $code,
        public string $severity,
        public string $category,
        public ?string $context,
    ) {
    }
}
