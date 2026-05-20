<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reporting\GetAuditStatus;

final readonly class GetAuditStatusQuery
{
    public function __construct(public string $auditId)
    {
    }
}
