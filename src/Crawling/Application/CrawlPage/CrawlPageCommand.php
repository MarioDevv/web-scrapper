<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application\CrawlPage;

final readonly class CrawlPageCommand
{
    public function __construct(
        public string $auditId,
        public string $url,
        public int $depth,
    ) {
    }
}
