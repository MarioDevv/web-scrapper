<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application;

final readonly class AuditCrawlConfig
{
    public function __construct(
        public string $seedUrl,
        public ?string $customUserAgent,
        public bool $respectRobotsTxt,
        public bool $ingestSitemaps,
        public int $concurrency,
        public float $requestDelay,
        public int $maxDepth,
        public bool $crawlResources,
        public int $maxPages,
    ) {
    }
}
