<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Robots;

use SeoSpider\Audit\Domain\Model\RobotsPolicy;
use SeoSpider\Audit\Domain\Model\Url;

final class NullRobotsPolicy implements RobotsPolicy
{
    public function load(Url $baseUrl): void
    {
    }

    public function isAllowed(Url $url): bool
    {
        return true;
    }

    public function crawlDelay(): ?float
    {
        return null;
    }

    /** @return Url[] */
    public function sitemapUrls(): array
    {
        return [];
    }
}