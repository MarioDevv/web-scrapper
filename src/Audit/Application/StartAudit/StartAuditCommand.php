<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\StartAudit;

final readonly class StartAuditCommand
{
    /**
     * @param string[] $excludePatterns
     * @param string[] $includePatterns
     */
    public function __construct(
        public string $seedUrl,
        public int $maxPages = 500,
        public int $maxDepth = 10,
        public int $concurrency = 5,
        public float $requestDelay = 0.25,
        public bool $respectRobotsTxt = true,
        public ?string $customUserAgent = null,
        public array $excludePatterns = [],
        public array $includePatterns = [],
        public bool $followExternalLinks = false,
        public bool $crawlSubdomains = false,
    ) {
    }
}
