<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Acl;

use SeoSpider\Auditing\Domain\Model\Analysis\SiteContext;
use SeoSpider\Auditing\Domain\Model\Analysis\SiteContextFactory;
use SeoSpider\Crawling\Domain\Model\Page\PageRepository;

final readonly class CrawlingSiteContextFactory implements SiteContextFactory
{
    public function __construct(private PageRepository $pages)
    {
    }

    public function forAudit(string $auditId, string $seedUrl): ?SiteContext
    {
        $pages = $this->pages->findByAudit($auditId);
        if ($pages === []) {
            return null;
        }

        return new LegacySiteContext(
            auditId: $auditId,
            seedUrl: $seedUrl,
            pages: $pages,
        );
    }
}
