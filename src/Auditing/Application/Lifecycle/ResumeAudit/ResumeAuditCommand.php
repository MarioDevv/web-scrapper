<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Lifecycle\ResumeAudit;

final readonly class ResumeAuditCommand
{
    public function __construct(public string $auditId)
    {
    }
}
