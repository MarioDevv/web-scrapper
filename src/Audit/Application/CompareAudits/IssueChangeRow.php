<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\CompareAudits;

final readonly class IssueChangeRow
{
    public function __construct(
        public string $pageUrl,
        public ?string $movedFromUrl,
        public string $code,
        public string $title,
        public string $severity,
    ) {
    }
}
