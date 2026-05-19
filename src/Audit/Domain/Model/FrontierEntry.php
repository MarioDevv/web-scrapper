<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;
use SeoSpider\Crawling\Domain\Model\DiscoverySource;

final readonly class FrontierEntry
{
    public function __construct(
        public Url $url,
        public int $depth,
    ) {
    }
}
