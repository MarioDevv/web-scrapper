<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

enum AuditStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::FAILED], true);
    }
}
