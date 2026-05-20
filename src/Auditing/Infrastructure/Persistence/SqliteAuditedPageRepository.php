<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Persistence;

use PDO;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueRuleCatalog;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final readonly class SqliteAuditedPageRepository implements AuditedPageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByAuditAndUrl(string $auditId, string $url): ?AuditedPage
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM pages WHERE audit_id = :audit_id AND url = :url',
        );
        $stmt->execute(['audit_id' => $auditId, 'url' => $url]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $page = AuditedPage::forUrl($auditId, $url);

        $issuesStmt = $this->pdo->prepare(
            'SELECT id, category, severity, code, message, context, catalog_version
             FROM issues WHERE page_id = :page_id',
        );
        $issuesStmt->execute(['page_id' => $row['id']]);

        foreach ($issuesStmt->fetchAll() as $issueRow) {
            $page->recordIssue(new Issue(
                id: new IssueId($issueRow['id']),
                category: IssueCategory::from($issueRow['category']),
                severity: IssueSeverity::from($issueRow['severity']),
                code: $issueRow['code'],
                message: $issueRow['message'],
                context: $issueRow['context'],
                catalogVersion: $issueRow['catalog_version'],
            ));
        }

        return $page;
    }

    public function save(AuditedPage $page): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM pages WHERE audit_id = :audit_id AND url = :url',
        );
        $stmt->execute(['audit_id' => $page->auditId(), 'url' => $page->url()]);
        $row = $stmt->fetch();

        if ($row === false) {
            return;
        }

        $pageId = $row['id'];

        $this->pdo->prepare('DELETE FROM issues WHERE page_id = :page_id')
            ->execute(['page_id' => $pageId]);

        $insert = $this->pdo->prepare(
            'INSERT INTO issues (id, page_id, category, severity, code, catalog_version, message, context)
             VALUES (:id, :page_id, :category, :severity, :code, :catalog_version, :message, :context)',
        );

        foreach ($page->issues() as $issue) {
            $insert->execute([
                'id' => $issue->id()->value(),
                'page_id' => $pageId,
                'category' => $issue->category()->value,
                'severity' => $issue->severity()->value,
                'code' => $issue->code(),
                'catalog_version' => $issue->catalogVersion() ?? IssueRuleCatalog::VERSION,
                'message' => $issue->message(),
                'context' => $issue->context(),
            ]);
        }
    }

    public function issueCodesByUrl(string $auditId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.url AS url, i.code AS code
             FROM pages p JOIN issues i ON i.page_id = p.id
             WHERE p.audit_id = :audit_id',
        );
        $stmt->execute(['audit_id' => $auditId]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['url']][] = $row['code'];
        }

        return $map;
    }
}
