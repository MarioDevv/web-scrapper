<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reporting\CompareAudits;

use SeoSpider\Auditing\Domain\Model\Audit\AuditId;

final readonly class CompareAuditsQuery
{
    public function __construct(
        public AuditId $baseAuditId,
        public AuditId $targetAuditId,
    ) {
    }
}
