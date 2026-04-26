<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Frontier;

use PDO;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\DiscoverySource;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\FrontierEntry;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;

final readonly class SqliteFrontier implements Frontier
{
    public function __construct(
        private PDO $pdo,
        private UrlCanonicalizer $canonicalizer,
    ) {
    }

    public function enqueue(AuditId $auditId, Url $url, int $depth, DiscoverySource $source): bool
    {
        $stmt = $this->pdo->prepare('
            INSERT OR IGNORE INTO frontier (audit_id, url, depth, status, source)
            VALUES (:audit_id, :url, :depth, :status, :source)
        ');

        $stmt->execute([
            'audit_id' => $auditId->value(),
            'url' => $this->canonicalizer->canonicalize($url)->toString(),
            'depth' => $depth,
            'status' => 'pending',
            'source' => $source->value,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function dequeue(AuditId $auditId): ?FrontierEntry
    {
        $batch = $this->dequeueBatch($auditId, 1);

        return $batch[0] ?? null;
    }

    public function dequeueBatch(AuditId $auditId, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $this->pdo->beginTransaction();

        try {
            $select = $this->pdo->prepare('
                SELECT id, url, depth FROM frontier
                WHERE audit_id = :audit_id AND status = :status
                ORDER BY id ASC
                LIMIT :limit
            ');
            $select->bindValue(':audit_id', $auditId->value());
            $select->bindValue(':status', 'pending');
            $select->bindValue(':limit', $count, PDO::PARAM_INT);
            $select->execute();

            $rows = $select->fetchAll();
            if ($rows === []) {
                $this->pdo->commit();

                return [];
            }

            $ids = array_map(static fn(array $row) => (int) $row['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $update = $this->pdo->prepare(
                "UPDATE frontier SET status = 'processing' WHERE id IN ({$placeholders})"
            );
            $update->execute($ids);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return array_map(
            static fn(array $row) => new FrontierEntry(
                Url::fromString($row['url']),
                (int) $row['depth'],
            ),
            $rows,
        );
    }

    public function markVisited(AuditId $auditId, Url $url): void
    {
        $canonical = $this->canonicalizer->canonicalize($url)->toString();

        $stmt = $this->pdo->prepare('
            UPDATE frontier SET status = :status
            WHERE audit_id = :audit_id AND url = :url
        ');

        $stmt->execute([
            'status' => 'visited',
            'audit_id' => $auditId->value(),
            'url' => $canonical,
        ]);

        if ($stmt->rowCount() === 0) {
            $this->pdo->prepare('
                INSERT OR IGNORE INTO frontier (audit_id, url, depth, status, source)
                VALUES (:audit_id, :url, 0, :status, :source)
            ')->execute([
                'audit_id' => $auditId->value(),
                'url' => $canonical,
                'status' => 'visited',
                'source' => DiscoverySource::LINK->value,
            ]);
        }
    }

    public function isKnown(AuditId $auditId, Url $url): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT 1 FROM frontier WHERE audit_id = :audit_id AND url = :url LIMIT 1
        ');

        $stmt->execute([
            'audit_id' => $auditId->value(),
            'url' => $this->canonicalizer->canonicalize($url)->toString(),
        ]);

        return $stmt->fetch() !== false;
    }

    public function isEmpty(AuditId $auditId): bool
    {
        return $this->pendingCount($auditId) === 0;
    }

    public function clear(AuditId $auditId): void
    {
        $this->pdo->prepare('DELETE FROM frontier WHERE audit_id = :audit_id')
            ->execute(['audit_id' => $auditId->value()]);
    }

    public function pendingCount(AuditId $auditId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM frontier WHERE audit_id = :audit_id AND status = :status
        ');

        $stmt->execute([
            'audit_id' => $auditId->value(),
            'status' => 'pending',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function urlsBySource(AuditId $auditId, DiscoverySource $source): array
    {
        $stmt = $this->pdo->prepare('
            SELECT url FROM frontier WHERE audit_id = :audit_id AND source = :source
        ');

        $stmt->execute([
            'audit_id' => $auditId->value(),
            'source' => $source->value,
        ]);

        return array_map(
            static fn(array $row) => Url::fromString($row['url']),
            $stmt->fetchAll() ?: [],
        );
    }
}