<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Persistence;

use PDO;
use SeoSpider\Audit\Application\GetAuditIssueReport\IssueReportData;
use SeoSpider\Audit\Application\GetAuditIssueReport\IssueReportReader;
use SeoSpider\Audit\Application\GetAuditIssueReport\IssueReportRow;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;

final readonly class SqliteIssueReportReader implements IssueReportReader
{
    public function __construct(private PDO $pdo)
    {
    }

    public function read(AuditId $auditId): IssueReportData
    {
        $rowsStmt = $this->pdo->prepare('
            SELECT i.code, i.severity, i.category, i.context, p.id AS page_id, p.url
            FROM issues i
            JOIN pages p ON p.id = i.page_id
            WHERE p.audit_id = :audit_id
        ');
        $rowsStmt->execute(['audit_id' => $auditId->value()]);

        $rows = array_map(
            static fn(array $r) => new IssueReportRow(
                pageId: $r['page_id'],
                pageUrl: $r['url'],
                code: $r['code'],
                severity: $r['severity'],
                category: $r['category'],
                context: $r['context'],
            ),
            $rowsStmt->fetchAll() ?: [],
        );

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM pages WHERE audit_id = :audit_id');
        $countStmt->execute(['audit_id' => $auditId->value()]);

        return new IssueReportData(
            rows: $rows,
            pageCount: (int) $countStmt->fetchColumn(),
        );
    }
}
