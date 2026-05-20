<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Acl;

use SeoSpider\Auditing\Domain\Model\Analysis\RobotsCheck;
use SeoSpider\Crawling\Application\RobotsPolicy;
use SeoSpider\Crawling\Domain\Model\Url;

final readonly class CrawlingRobotsCheck implements RobotsCheck
{
    public function __construct(private RobotsPolicy $policy)
    {
    }

    public function load(string $seedUrl): void
    {
        $this->policy->load(Url::fromString($seedUrl));
    }

    public function isAllowed(string $url): bool
    {
        return $this->policy->isAllowed(Url::fromString($url));
    }
}
