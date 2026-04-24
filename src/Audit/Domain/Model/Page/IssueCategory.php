<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

enum IssueCategory: string
{
    case LINKS = 'links';
    case METADATA = 'metadata';
    case DIRECTIVES = 'directives';
    case HREFLANG = 'hreflang';
    case CONTENT = 'content';
    case PERFORMANCE = 'performance';
    case SECURITY = 'security';
}
