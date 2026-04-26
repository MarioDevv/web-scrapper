<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditSnapshot;
use SeoSpider\Audit\Domain\Model\Audit\AuditSnapshotRepository;

final readonly class SqliteAuditSnapshotRepository implements AuditSnapshotRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(AuditSnapshot $snapshot): void
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO audit_snapshots (audit_id, overview_json, generated_at)
            VALUES (:audit_id, :overview, :generated_at)
            ON CONFLICT(audit_id) DO UPDATE SET
                overview_json = :overview,
                generated_at = :generated_at
        SQL);

        $stmt->execute([
            'audit_id' => $snapshot->auditId->value(),
            'overview' => json_encode($snapshot->overview, JSON_THROW_ON_ERROR),
            'generated_at' => $snapshot->generatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByAudit(AuditId $auditId): ?AuditSnapshot
    {
        $stmt = $this->pdo->prepare(
            'SELECT overview_json, generated_at FROM audit_snapshots WHERE audit_id = :audit_id',
        );
        $stmt->execute(['audit_id' => $auditId->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $overview = json_decode($row['overview_json'], true, flags: JSON_THROW_ON_ERROR);

        return new AuditSnapshot(
            auditId: $auditId,
            overview: is_array($overview) ? $overview : [],
            generatedAt: new DateTimeImmutable($row['generated_at']),
        );
    }
}
