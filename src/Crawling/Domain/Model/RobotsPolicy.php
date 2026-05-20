<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model;
use SeoSpider\Crawling\Domain\Model\Url;

interface RobotsPolicy
{
    public function load(Url $baseUrl): void;

    public function isAllowed(Url $url): bool;

    public function crawlDelay(): ?float;

    /** @return Url[] */
    public function sitemapUrls(): array;
}
