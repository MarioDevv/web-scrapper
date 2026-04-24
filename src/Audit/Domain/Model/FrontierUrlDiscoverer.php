<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Audit\AuditConfiguration;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Page;

final readonly class FrontierUrlDiscoverer implements UrlDiscoverer
{
    public function __construct(private Frontier $frontier)
    {
    }

    public function discoverFrom(
        Page $page,
        AuditId $auditId,
        int $currentDepth,
        AuditConfiguration $config,
    ): int {
        $nextDepth = $currentDepth + 1;

        if ($nextDepth > $config->maxDepth) {
            return 0;
        }

        $newUrls = 0;

        foreach ($page->internalLinks() as $link) {
            $isEnqueuableAnchor = $link->isAnchor() && $link->isFollowable();
            $isEnqueuableResource = $config->crawlResources && $link->isResource();

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
