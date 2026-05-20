<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

use SeoSpider\Auditing\Domain\Model\Audit\AuditId;

interface PageRowReader
{
    /** @return PageRow[] */
    public function forAudit(AuditId $auditId): array;
}
