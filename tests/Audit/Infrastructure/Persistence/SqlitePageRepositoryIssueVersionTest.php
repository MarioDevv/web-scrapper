<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Persistence;

use PDO;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueRuleCatalog;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Infrastructure\Persistence\SqlitePageRepository;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\PageFixture;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\SqliteSchemaFactory;

final class SqlitePageRepositoryIssueVersionTest extends TestCase
{
    private PDO $pdo;
    private SqlitePageRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = SqliteSchemaFactory::inMemoryWithFullSchema();
        $this->repo = new SqlitePageRepository($this->pdo);
    }

    public function test_persists_current_catalog_version_for_new_issues(): void
    {
        $auditId = AuditId::generate();
        SqliteSchemaFactory::insertAuditRow($this->pdo, $auditId, 'https://example.com/');

        $page = PageFixture::buildWithIssue(
            auditId: $auditId,
            issue: new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::ERROR,
                code: 'title_missing',
                message: 'No <title>.',
            ),
        );

        $this->repo->save($page);

        $reloaded = $this->repo->findById($page->id());
        self::assertNotNull($reloaded);

        $issues = $reloaded->issues();
        self::assertCount(1, $issues);
        self::assertSame(IssueRuleCatalog::VERSION, $issues[0]->catalogVersion());
    }
}
