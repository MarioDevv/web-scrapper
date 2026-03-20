<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\StartAudit;

final readonly class StartAuditResponse
{
    public function __construct(
        public string $auditId,
        public string $seedUrl,
        public string $status,
    ) {
    }
}
