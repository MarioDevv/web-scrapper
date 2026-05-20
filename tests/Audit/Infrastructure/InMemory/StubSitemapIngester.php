<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;


use SeoSpider\Crawling\Domain\Model\DiscoverySource;
use SeoSpider\Crawling\Application\Frontier;
use SeoSpider\Crawling\Application\SitemapIngester;
use SeoSpider\Crawling\Domain\Model\Url;

final class StubSitemapIngester implements SitemapIngester
{
    /** @var Url[] */
    private array $urls = [];

    public function __construct(private readonly ?Frontier $frontier = null)
    {
    }

    /** @param Url[] $urls */
    public function withUrls(array $urls): void
    {
        $this->urls = $urls;
    }

    public function ingest(string $auditId, Url $seedUrl, ?string $userAgent = null): int
    {
        if ($this->frontier === null || $this->urls === []) {
            return 0;
        }

        $added = 0;
        foreach ($this->urls as $url) {
            if ($this->frontier->enqueue($auditId, $url, depth: 0, source: DiscoverySource::SITEMAP)) {
                $added++;
            }
        }

        return $added;
    }
}
