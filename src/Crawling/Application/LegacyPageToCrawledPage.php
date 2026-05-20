<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application;

use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Crawling\Domain\Model\Page\CrawledPage;

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
