<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Url;

final readonly class SiteAuditContext
{
    /** @param Page[] $pages */
    public function __construct(
        public AuditId $auditId,
        public Url $seedUrl,
        public array $pages,
    ) {
    }

    public function pageByUrl(Url $url): ?Page
    {
        foreach ($this->pages as $page) {
            if ($page->url()->equals($url)) {
                return $page;
            }
            $finalUrl = $page->response()->finalUrl();
            if ($finalUrl !== null && $finalUrl->equals($url)) {
                return $page;
            }
        }

        return null;
    }
}
