<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Infrastructure\Persistence;

use PDO;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Infrastructure\Persistence\SqlitePageRepository;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueRuleCatalog;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Auditing\Infrastructure\Persistence\SqliteAuditedPageRepository;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\PageFixture;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\SqliteSchemaFactory;

/**
 * Characterization: the Auditing AuditedPage reconstituted from the
 * shared rows must carry exactly the findings the legacy crawl/analysis
 * path persisted, with a score consistent with them.
 */
final class SqliteAuditedPageRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = SqliteSchemaFactory::inMemoryWithFullSchema();
    }

    public function test_reconstitutes_audited_page_equivalent_to_persisted_issues(): void
    {
        $auditId = AuditId::generate();
        SqliteSchemaFactory::insertAuditRow($this->pdo, $auditId, 'https://example.com/');

        $issue = new Issue(
            id: IssueId::generate(),
            category: IssueCategory::METADATA,
            severity: IssueSeverity::ERROR,
            code: 'title_missing',
            message: 'No <title>.',
        );
        $legacyPage = PageFixture::buildWithIssue(
            auditId: $auditId,
            issue: $issue,
            url: 'https://example.com/about',
        );
        (new SqlitePageRepository($this->pdo))->save($legacyPage);

        $audited = (new SqliteAuditedPageRepository($this->pdo))
            ->findByAuditAndUrl($auditId->value(), 'https://example.com/about');

        $this->assertNotNull($audited);
        $this->assertSame(
            ['title_missing'],
            array_map(static fn (Issue $i): string => $i->code(), $audited->issues()),
        );
        $this->assertSame(IssueSeverity::ERROR, $audited->issues()[0]->severity());
        $this->assertSame(1, $audited->errorCount());

        $expectedWeight = IssueRuleCatalog::forCode('title_missing')?->weight() ?? 10;
        $this->assertSame(max(0, 100 - $expectedWeight), $audited->score()->value());
    }

    public function test_returns_null_for_unknown_page(): void
    {
        $auditId = AuditId::generate();
        SqliteSchemaFactory::insertAuditRow($this->pdo, $auditId, 'https://example.com/');

        $audited = (new SqliteAuditedPageRepository($this->pdo))
            ->findByAuditAndUrl($auditId->value(), 'https://example.com/missing');

        $this->assertNull($audited);
    }
}
