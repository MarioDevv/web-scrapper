<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Audit\AuditConfiguration;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Page;

/**
 * Decides which of a page's internal links are worth adding to the crawl
 * frontier. This is domain policy — depth limits, anchor vs resource, the
 * crawlResources flag — that used to live as a private method in the crawl
 * command handler and now has a name of its own.
 */
interface UrlDiscoverer
{
    /** Returns the count of URLs newly enqueued (deduped) from this page. */
    public function discoverFrom(
        Page $page,
        AuditId $auditId,
        int $currentDepth,
        AuditConfiguration $config,
    ): int;
}
