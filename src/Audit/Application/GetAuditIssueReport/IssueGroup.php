<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditIssueReport;

final readonly class IssueGroup
{
    /** @param AffectedPage[] $affectedPages */
    public function __construct(
        public string $code,
        public string $category,
        public string $severity,
        public ?string $title,
        public ?string $summary,
        public ?string $why,
        public ?string $how,
        public ?string $source,
        public int $count,
        public int $affectedPageCount,
        public array $affectedPages,
        public int $weight = 0,
    ) {
    }
}
