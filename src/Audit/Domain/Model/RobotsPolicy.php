<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

interface RobotsPolicy
{
    public function load(Url $baseUrl): void;

    public function isAllowed(Url $url): bool;

    public function crawlDelay(): ?float;

    /** @return Url[] */
    public function sitemapUrls(): array;
}
