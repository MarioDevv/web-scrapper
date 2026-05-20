<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Acl;

use SeoSpider\Auditing\Domain\Model\Reporting\PendingUrlCounter;
use SeoSpider\Crawling\Domain\Model\Frontier;

final readonly class FrontierBackedPendingUrlCounter implements PendingUrlCounter
{
    public function __construct(private Frontier $frontier)
    {
    }

    public function forAudit(string $auditId): int
    {
        return $this->frontier->pendingCount($auditId);
    }
}
