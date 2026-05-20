<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model;

use SeoSpider\Crawling\Domain\Model\CrawlPolicy;

use SeoSpider\Crawling\Domain\Model\Page\CrawledPage;

interface UrlDiscoverer
{
    public function discoverFrom(
        CrawledPage $page,
        string $auditId,
        int $currentDepth,
        CrawlPolicy $policy,
    ): int;
}
