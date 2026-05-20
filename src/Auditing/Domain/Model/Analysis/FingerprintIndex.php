<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint;

interface FingerprintIndex
{
    /** @return array<string, Fingerprint> keyed by page URL */
    public function forAudit(string $auditId): array;
}
