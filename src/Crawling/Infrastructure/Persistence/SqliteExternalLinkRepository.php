<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Infrastructure\Persistence;

use PDO;
use SeoSpider\Crawling\Domain\Model\ExternalLink\ExternalLinkRepository;
use SeoSpider\Crawling\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\Url;

final readonly class SqliteExternalLinkRepository implements ExternalLinkRepository
{
    public function __construct(private PDO $pdo) {}

    public function exists(string $auditId, Url $url): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM external_url_checks WHERE audit_id = ? AND url = ? LIMIT 1'
        );
        $stmt->execute([$auditId, $url->toString()]);

        return $stmt->fetch() !== false;
    }

    public function save(
        string $auditId,
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
            $auditId,
            $url->toString(),
            $statusCode,
            $responseTime,
            $error,
            $sourcePageId->value(),
            $anchorText,
        ]);
    }
}