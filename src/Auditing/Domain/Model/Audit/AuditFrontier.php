<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Audit;

interface AuditFrontier
{
    public function enqueueSeed(string $auditId, string $seedUrl): void;

    public function clear(string $auditId): void;
}
