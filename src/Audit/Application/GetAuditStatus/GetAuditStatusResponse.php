<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditStatus;

final readonly class GetAuditStatusResponse
{
    public function __construct(
        public string $auditId,
        public string $seedUrl,
        public string $status,
        public int $pagesDiscovered,
        public int $pagesCrawled,
        public int $pagesFailed,
        public int $issuesFound,
        public int $errorsFound,
        public int $warningsFound,
        public int $maxPages,
        public int $pendingUrls,
        public ?string $startedAt,
        public ?string $completedAt,
        public ?float $duration,
    ) {
    }
}
