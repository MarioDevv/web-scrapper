<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model\Page;

enum DirectiveSource: string
{
    case META_TAG = 'meta_tag';
    case HTTP_HEADER = 'http_header';
}
