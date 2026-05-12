<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

enum IssueChange: string
{
    case ADDED = 'added';
    case REMOVED = 'removed';
    case PERSISTENT = 'persistent';
}
