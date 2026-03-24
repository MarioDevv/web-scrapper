<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Persistence;

use PDO;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\ExternalLinkRepository;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Url;

final readonly class SqliteExternalLinkRepository implements ExternalLinkRepository
{
    public function __construct(private PDO $pdo) {}

    public function exists(AuditId $auditId, Url $url): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM external_url_checks WHERE audit_id = ? AND url = ? LIMIT 1'
        );
        $stmt->execute([$auditId->value(), $url->toString()]);

        return $stmt->fetch() !== false;
    }

    public function save(
        AuditId $auditId,
        Url $url,
        int $statusCode,
        float $responseTime,
        ?string $error,
        PageId $sourcePageId,
        ?string $anchorText,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO external_url_checks (audit_id, url, status_code, response_time, error, source_page_id, anchor_text)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $auditId->value(),
            $url->toString(),
            $statusCode,
            $responseTime,
            $error,
            $sourcePageId->value(),
            $anchorText,
        ]);
    }
}