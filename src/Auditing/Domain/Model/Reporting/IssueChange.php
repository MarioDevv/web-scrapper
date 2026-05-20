<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

enum IssueChange: string
{
    case ADDED = 'added';
    case REMOVED = 'removed';
    case PERSISTENT = 'persistent';
}
