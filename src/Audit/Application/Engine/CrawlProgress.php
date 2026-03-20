<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\Engine;

final readonly class CrawlProgress
{
    public function __construct(
        public string $auditId,
        public string $currentUrl,
        public int $pagesCrawled,
        public int $pagesFailed,
        public int $pagesDiscovered,
        public int $pendingUrls,
        public int $maxPages,
        public string $status,
    ) {
    }
}