<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditPages;

final readonly class GetAuditPagesQuery
{
    public function __construct(public string $auditId)
    {
    }
}
