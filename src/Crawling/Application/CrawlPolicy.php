<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application;

final readonly class CrawlPolicy
{
    public function __construct(
        public int $maxDepth,
        public bool $crawlResources,
    ) {
    }
}
