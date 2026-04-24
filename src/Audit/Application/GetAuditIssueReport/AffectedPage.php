<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

final readonly class AffectedPage
{
    public function __construct(
        public string $pageId,
        public string $url,
        public ?string $context,
    ) {
    }
}
