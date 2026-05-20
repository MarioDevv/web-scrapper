<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model;

use SeoSpider\Crawling\Domain\Model\Url;

interface SitemapIngester
{
    public function ingest(string $auditId, Url $seedUrl, ?string $userAgent = null): int;
}
