<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reporting\GetAuditPages;

final readonly class GetAuditPagesResponse
{
    /** @param PageSummary[] $pages */
    public function __construct(
        public string $auditId,
        public array $pages,
        public int $total,
    ) {
    }
}
