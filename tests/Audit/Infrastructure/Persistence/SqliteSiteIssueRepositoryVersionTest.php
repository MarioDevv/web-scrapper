<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Persistence;

use PDO;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueRuleCatalog;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\SiteIssue;
use SeoSpider\Audit\Infrastructure\Persistence\SqliteSiteIssueRepository;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\SqliteSchemaFactory;

final class SqliteSiteIssueRepositoryVersionTest extends TestCase
{
    public function test_persists_and_hydrates_catalog_version(): void
    {
        $pdo = SqliteSchemaFactory::inMemoryWithFullSchema();
        $repo = new SqliteSiteIssueRepository($pdo);

        $auditId = AuditId::generate();
        SqliteSchemaFactory::insertAuditRow($pdo, $auditId, 'https://example.com/');

        $repo->appendIssues($auditId, [
            new SiteIssue(
                id: IssueId::generate(),
                category: IssueCategory::HREFLANG,
                severity: IssueSeverity::WARNING,
                code: 'hreflang_no_return',
                message: 'A → B without return.',
            ),
        ]);

        $reloaded = $repo->findByAudit($auditId);
        self::assertCount(1, $reloaded);
        self::assertSame(IssueRuleCatalog::VERSION, $reloaded[0]->catalogVersion);
    }
}
