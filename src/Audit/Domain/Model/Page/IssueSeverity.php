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

    public function rank(): int
    {
        return match ($this) {
            self::ERROR   => 0,
            self::WARNING => 1,
            self::NOTICE  => 2,
            self::INFO    => 3,
        };
    }
}
