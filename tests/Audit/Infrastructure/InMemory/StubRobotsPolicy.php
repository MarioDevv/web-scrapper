<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\RobotsPolicy;
use SeoSpider\Audit\Domain\Model\Url;

final class StubRobotsPolicy implements RobotsPolicy
{
    /** @var array<string, true> */
    private array $disallowed = [];
    private ?float $crawlDelay = null;

    public function load(Url $baseUrl): void
    {
    }

    public function disallow(string $url): void
    {
        $this->disallowed[$url] = true;
    }

    public function withCrawlDelay(float $delay): void
    {
        $this->crawlDelay = $delay;
    }

    public function isAllowed(Url $url): bool
    {
        return !isset($this->disallowed[$url->toString()]);
    }

    public function crawlDelay(): ?float
    {
        return $this->crawlDelay;
    }

    /** @return Url[] */
    public function sitemapUrls(): array
    {
        return [];
    }
}