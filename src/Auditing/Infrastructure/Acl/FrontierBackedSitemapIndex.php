<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Acl;

use SeoSpider\Auditing\Domain\Model\Analysis\SitemapIndex;
use SeoSpider\Crawling\Domain\Model\Frontier;
use SeoSpider\Crawling\Domain\Model\DiscoverySource;
use SeoSpider\Crawling\Domain\Model\Url;

final readonly class FrontierBackedSitemapIndex implements SitemapIndex
{
    public function __construct(private Frontier $frontier)
    {
    }

    public function urlsFor(string $auditId): array
    {
        return array_map(
            static fn (Url $url): string => $url->toString(),
            $this->frontier->urlsBySource($auditId, DiscoverySource::SITEMAP),
        );
    }
}
