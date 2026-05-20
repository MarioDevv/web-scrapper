<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Lifecycle\CancelAudit;

final readonly class CancelAuditCommand
{
    public function __construct(public string $auditId)
    {
    }
}
