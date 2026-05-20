<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Persistence;

use PDO;
use SeoSpider\Auditing\Domain\Model\Analysis\FingerprintIndex;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint;

final readonly class SqliteFingerprintIndex implements FingerprintIndex
{
    public function __construct(private PDO $pdo)
    {
    }

    public function forAudit(string $auditId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT url, exact_hash, sim_hash FROM pages WHERE audit_id = :audit_id AND exact_hash IS NOT NULL',
        );
        $stmt->execute(['audit_id' => $auditId]);

        $fingerprints = [];
        foreach ($stmt->fetchAll() as $row) {
            $fingerprints[$row['url']] = new Fingerprint($row['exact_hash'], (int) $row['sim_hash']);
        }

        return $fingerprints;
    }
}
