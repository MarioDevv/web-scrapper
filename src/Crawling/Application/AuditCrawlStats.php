<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application;

final readonly class AuditCrawlStats
{
    public function __construct(
        public int $pagesCrawled,
        public int $pagesFailed,
        public int $pagesDiscovered,
    ) {
    }
}
