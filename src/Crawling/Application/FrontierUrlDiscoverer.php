<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application;

use SeoSpider\Crawling\Domain\Model\DiscoverySource;
use SeoSpider\Crawling\Domain\Model\Page\CrawledPage;

final readonly class FrontierUrlDiscoverer implements UrlDiscoverer
{
    public function __construct(private Frontier $frontier)
    {
    }

    public function discoverFrom(
        CrawledPage $page,
        string $auditId,
        int $currentDepth,
        CrawlPolicy $policy,
    ): int {
        $nextDepth = $currentDepth + 1;

        if ($nextDepth > $policy->maxDepth) {
            return 0;
        }

        $newUrls = 0;

        foreach ($page->internalLinks() as $link) {
            $isEnqueuableAnchor = $link->isAnchor() && $link->isFollowable();
            $isEnqueuableResource = $policy->crawlResources && $link->isResource();

            if (!$isEnqueuableAnchor && !$isEnqueuableResource) {
                continue;
            }

            if ($this->frontier->enqueue($auditId, $link->targetUrl(), $nextDepth, DiscoverySource::LINK)) {
                $newUrls++;
            }
        }

        return $newUrls;
    }
}
