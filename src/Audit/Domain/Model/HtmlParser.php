<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Page\Directive;
use SeoSpider\Audit\Domain\Model\Page\Hreflang;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\PageMetadata;

interface HtmlParser
{
    public function extractMetadata(string $html): PageMetadata;

    public function extractDirectives(string $html, Url $baseUrl): Directive;

    /** @return Link[] */
    public function extractLinks(string $html, Url $baseUrl): array;

    /** @return Hreflang[] */
    public function extractHreflangs(string $html, Url $baseUrl): array;

    public function extractCleanContent(string $html): string;
}
