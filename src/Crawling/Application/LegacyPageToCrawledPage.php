<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application;

use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Crawling\Domain\Model\Page\CrawledPage;

/**
 * Transitional adapter: projects the legacy mutable Page aggregate onto
 * the immutable CrawledPage, dropping the Auditing-owned issues. Used by
 * the 3b characterization test (and, from 3d, by the crawl hot path)
 * until the legacy Page is retired. Importing legacy SeoSpider\Audit
 * from Crawling is allowed by the boundary guardrail (it forbids only
 * Crawling<->Auditing); removed in Phase 5.
 *
 * @phase3
 */
final readonly class LegacyPageToCrawledPage
{
    public function __invoke(Page $page): CrawledPage
    {
        return new CrawledPage(
            url: $page->url(),
            response: $page->response(),
            redirectChain: $page->redirectChain(),
            crawlDepth: $page->crawlDepth(),
            metadata: $page->metadata(),
            directives: $page->directives(),
            fingerprint: $page->fingerprint(),
            links: $page->links(),
            hreflangs: $page->hreflangs(),
            crawledAt: $page->crawledAt(),
        );
    }
}
