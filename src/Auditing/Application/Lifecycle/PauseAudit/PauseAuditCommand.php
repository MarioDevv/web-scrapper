<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Lifecycle\PauseAudit;

final readonly class PauseAuditCommand
{
    public function __construct(public string $auditId)
    {
    }
}
