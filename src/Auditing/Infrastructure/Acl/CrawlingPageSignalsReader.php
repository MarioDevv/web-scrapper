<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Acl;

use SeoSpider\Auditing\Domain\Model\Analysis\PageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\PageSignalsReader;
use SeoSpider\Crawling\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\Page\PageRepository;

final readonly class CrawlingPageSignalsReader implements PageSignalsReader
{
    public function __construct(private PageRepository $pages)
    {
    }

    public function findById(string $pageId): ?PageSignals
    {
        $page = $this->pages->findById(new PageId($pageId));
        return $page === null ? null : new LegacyPageToPageSignals($page);
    }
}
