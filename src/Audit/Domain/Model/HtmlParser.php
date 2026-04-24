<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Page\ParsedPage;

interface HtmlParser
{
    public function parse(string $html, Url $baseUrl): ParsedPage;
}
