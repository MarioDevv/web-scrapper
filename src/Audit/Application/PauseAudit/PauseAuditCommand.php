<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\PauseAudit;

final readonly class PauseAuditCommand
{
    public function __construct(public string $auditId)
    {
    }
}
