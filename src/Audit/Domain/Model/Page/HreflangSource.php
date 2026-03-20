<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

enum HreflangSource: string
{
    case HTML_HEAD = 'html_head';
    case HTTP_HEADER = 'http_header';
    case SITEMAP = 'sitemap';
}
