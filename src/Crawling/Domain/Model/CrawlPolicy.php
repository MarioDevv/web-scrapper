<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model;

final readonly class CrawlPolicy
{
    public function __construct(
        public int $maxDepth,
        public bool $crawlResources,
    ) {
    }
}
