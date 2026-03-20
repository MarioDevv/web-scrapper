<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\CancelAudit;

final readonly class CancelAuditCommand
{
    public function __construct(public string $auditId)
    {
    }
}
