<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

interface PendingUrlCounter
{
    public function forAudit(string $auditId): int;
}
