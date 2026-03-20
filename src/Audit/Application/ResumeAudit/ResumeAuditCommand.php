<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\ResumeAudit;

final readonly class ResumeAuditCommand
{
    public function __construct(public string $auditId)
    {
    }
}
