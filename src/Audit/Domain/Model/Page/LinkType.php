<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

enum LinkType: string
{
    case ANCHOR = 'anchor';
    case IMAGE = 'image';
    case SCRIPT = 'script';
    case STYLESHEET = 'stylesheet';
    case IFRAME = 'iframe';
    case CANONICAL = 'canonical';
    case ALTERNATE = 'alternate';
}
