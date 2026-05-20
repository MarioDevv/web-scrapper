<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\FingerprintIndex;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint;

final class InMemoryFingerprintIndex implements FingerprintIndex
{
    /** @var array<string, array<string, Fingerprint>> */
    private array $byAudit = [];

    public function put(string $auditId, string $url, Fingerprint $fingerprint): void
    {
        $this->byAudit[$auditId][$url] = $fingerprint;
    }

    public function forAudit(string $auditId): array
    {
        return $this->byAudit[$auditId] ?? [];
    }
}
