<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditStatus;

final readonly class GetAuditStatusQuery
{
    public function __construct(public string $auditId)
    {
    }
}
