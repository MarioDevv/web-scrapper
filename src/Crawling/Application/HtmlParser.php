<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application;
use SeoSpider\Crawling\Domain\Model\Url;

use SeoSpider\Crawling\Domain\Model\Page\ParsedPage;

interface HtmlParser
{
    public function parse(string $html, Url $baseUrl): ParsedPage;
}
