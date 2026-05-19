<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Infrastructure\Persistence;

use PDO;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Infrastructure\Persistence\SqlitePageRepository;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueRuleCatalog;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Auditing\Infrastructure\Persistence\SqliteAuditedPageRepository;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\PageFixture;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\SqliteSchemaFactory;

/**
 * Since the 3d cutover the Auditing AuditedPageRepository is the sole
 * writer/reader of page findings. These tests exercise its round-trip
 * and the catalog-version stamping (re-homed from the retired
 * SqlitePageRepositoryIssueVersionTest, as SqlitePageRepository no
 * longer persists issues).
 */
final class SqliteAuditedPageRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->pdo = SqliteSchemaFactory::inMemoryWithFullSchema();
        $this->auditId = AuditId::generate();
        SqliteSchemaFactory::insertAuditRow($this->pdo, $this->auditId, 'https://example.com/');
    }

    /** Creates the crawl-side pages row (no issues — those are Auditing's now). */
    private function seedPageRow(string $url): void
    {
        $page = PageFixture::buildWithIssue(
            auditId: $this->auditId,
            issue: new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                code: 'ignored_by_legacy_save',
                message: 'x',
            ),
            url: $url,
        );
        (new SqlitePageRepository($this->pdo))->save($page);
    }

    private function issue(string $code, IssueSeverity $severity): Issue
    {
        return new Issue(
            id: IssueId::generate(),
            category: IssueCategory::METADATA,
            severity: $severity,
            code: $code,
            message: 'm',
        );
    }

    public function test_legacy_page_save_no_longer_persists_issues(): void
    {
        $this->seedPageRow('https://example.com/p');

        $audited = (new SqliteAuditedPageRepository($this->pdo))
            ->findByAuditAndUrl($this->auditId->value(), 'https://example.com/p');

        $this->assertNotNull($audited);
        $this->assertSame([], $audited->issues());
    }

    public function test_save_round_trips_findings_idempotently(): void
    {
        $this->seedPageRow('https://example.com/p');
        $repo = new SqliteAuditedPageRepository($this->pdo);

        $audited = AuditedPage::forUrl($this->auditId->value(), 'https://example.com/p');
        $audited->recordIssue($this->issue('title_missing', IssueSeverity::ERROR));
        $audited->recordIssue($this->issue('content_thin', IssueSeverity::WARNING));

        $repo->save($audited);
        $repo->save($audited); // idempotent: replace, not append

        $reloaded = $repo->findByAuditAndUrl($this->auditId->value(), 'https://example.com/p');
        $this->assertNotNull($reloaded);
        $codes = array_map(static fn (Issue $i): string => $i->code(), $reloaded->issues());
        sort($codes);
        $this->assertSame(['content_thin', 'title_missing'], $codes);
    }

    public function test_persists_active_catalog_version_for_new_issues(): void
    {
        $this->seedPageRow('https://example.com/p');
        $repo = new SqliteAuditedPageRepository($this->pdo);

        $audited = AuditedPage::forUrl($this->auditId->value(), 'https://example.com/p');
        $audited->recordIssue($this->issue('title_missing', IssueSeverity::ERROR));
        $repo->save($audited);

        $stmt = $this->pdo->query('SELECT catalog_version FROM issues');
        $this->assertSame(IssueRuleCatalog::VERSION, $stmt->fetchColumn());
    }

    public function test_save_is_a_noop_for_unknown_page(): void
    {
        $page = AuditedPage::forUrl($this->auditId->value(), 'https://example.com/ghost');
        $page->recordIssue($this->issue('content_thin', IssueSeverity::WARNING));

        (new SqliteAuditedPageRepository($this->pdo))->save($page); // must not throw

        $this->assertNull(
            (new SqliteAuditedPageRepository($this->pdo))
                ->findByAuditAndUrl($this->auditId->value(), 'https://example.com/ghost'),
        );
    }

    public function test_returns_null_for_unknown_page(): void
    {
        $audited = (new SqliteAuditedPageRepository($this->pdo))
            ->findByAuditAndUrl($this->auditId->value(), 'https://example.com/missing');

        $this->assertNull($audited);
    }
}
