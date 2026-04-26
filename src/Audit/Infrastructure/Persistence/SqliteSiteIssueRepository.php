<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Persistence;

use PDO;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\SiteIssue;
use SeoSpider\Audit\Domain\Model\Page\SiteIssueRepository;

final readonly class SqliteSiteIssueRepository implements SiteIssueRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param SiteIssue[] $issues */
    public function appendIssues(AuditId $auditId, array $issues): void
    {
        if ($issues === []) {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO site_issues (id, audit_id, category, severity, code, message, context)
            VALUES (:id, :audit_id, :category, :severity, :code, :message, :context)
        ');

        foreach ($issues as $issue) {
            $stmt->execute([
                'id' => $issue->id->value(),
                'audit_id' => $auditId->value(),
                'category' => $issue->category->value,
                'severity' => $issue->severity->value,
                'code' => $issue->code,
                'message' => $issue->message,
                'context' => $issue->context,
            ]);
        }
    }

    /** @return SiteIssue[] */
    public function findByAudit(AuditId $auditId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM site_issues WHERE audit_id = :audit_id');
        $stmt->execute(['audit_id' => $auditId->value()]);

        return array_map(
            static fn(array $row) => new SiteIssue(
                id: new IssueId($row['id']),
                category: IssueCategory::from($row['category']),
                severity: IssueSeverity::from($row['severity']),
                code: $row['code'],
                message: $row['message'],
                context: $row['context'],
            ),
            $stmt->fetchAll() ?: [],
        );
    }
}
