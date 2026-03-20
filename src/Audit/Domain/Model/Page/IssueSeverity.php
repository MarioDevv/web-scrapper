<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

enum IssueSeverity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
    case NOTICE = 'notice';
    case INFO = 'info';

    public function isBlocker(): bool
    {
        return $this === self::ERROR;
    }
}
