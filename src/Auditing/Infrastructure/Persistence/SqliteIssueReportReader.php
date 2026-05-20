<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Persistence;

use PDO;
use SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport\IssueReportData;
use SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport\IssueReportReader;
use SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport\IssueReportRow;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;

final readonly class SqliteIssueReportReader implements IssueReportReader
{
    public function __construct(private PDO $pdo)
    {
    }

    public function read(AuditId $auditId): IssueReportData
    {
        $pageIssuesStmt = $this->pdo->prepare('
            SELECT i.code, i.severity, i.category, i.context, p.id AS page_id, p.url
            FROM issues i
            JOIN pages p ON p.id = i.page_id
            WHERE p.audit_id = :audit_id
        ');
        $pageIssuesStmt->execute(['audit_id' => $auditId->value()]);

        $rows = array_map(
            static fn(array $r) => new IssueReportRow(
                pageId: $r['page_id'],
                pageUrl: $r['url'],
                code: $r['code'],
                severity: $r['severity'],
                category: $r['category'],
                context: $r['context'],
            ),
            $pageIssuesStmt->fetchAll() ?: [],
        );

        $siteIssuesStmt = $this->pdo->prepare('
            SELECT code, severity, category, context
            FROM site_issues
            WHERE audit_id = :audit_id
        ');
        $siteIssuesStmt->execute(['audit_id' => $auditId->value()]);

        foreach ($siteIssuesStmt->fetchAll() ?: [] as $r) {
            $rows[] = new IssueReportRow(
                pageId: null,
                pageUrl: null,
                code: $r['code'],
                severity: $r['severity'],
                category: $r['category'],
                context: $r['context'],
            );
        }

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM pages WHERE audit_id = :audit_id');
        $countStmt->execute(['audit_id' => $auditId->value()]);

        return new IssueReportData(
            rows: $rows,
            pageCount: (int) $countStmt->fetchColumn(),
        );
    }
}
