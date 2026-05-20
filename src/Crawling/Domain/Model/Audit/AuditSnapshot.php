<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model\Audit;

final readonly class AuditSnapshot
{
    public function __construct(
        public string $auditId,
        public string $status,
        public AuditCrawlConfig $config,
        public AuditCrawlStats $stats,
        public bool $canAcceptMorePages,
        public bool $isRunning,
        public bool $isFinished,
    ) {
    }
}
