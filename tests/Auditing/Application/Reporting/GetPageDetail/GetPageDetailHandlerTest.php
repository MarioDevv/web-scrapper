<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Application\Reporting\GetPageDetail;

use PDO;
use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Infrastructure\Acl\CrawlingPageDetailReader;
use SeoSpider\Auditing\Application\Reporting\GetPageDetail\GetPageDetailHandler;
use SeoSpider\Auditing\Application\Reporting\GetPageDetail\GetPageDetailQuery;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Crawling\Infrastructure\Persistence\SqlitePageRepository;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Auditing\Infrastructure\Persistence\SqliteAuditedPageRepository;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\PageFixture;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\SqliteSchemaFactory;

final class GetPageDetailHandlerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = SqliteSchemaFactory::inMemoryWithFullSchema();
    }

    public function test_returns_findings_owned_by_the_auditing_context(): void
    {
        $auditId = AuditId::generate();
        SqliteSchemaFactory::insertAuditRow($this->pdo, $auditId, 'https://example.com/');

        $page = PageFixture::buildWithIssue(
            auditId: $auditId,
            issue: new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                code: 'legacy_ignored',
                message: 'not persisted by crawl save anymore',
            ),
            url: 'https://example.com/p',
        );
        $pageRepo = new SqlitePageRepository($this->pdo);
        $pageRepo->save($page);

        $auditedRepo = new SqliteAuditedPageRepository($this->pdo);
        $audited = AuditedPage::forUrl($auditId->value(), 'https://example.com/p');
        $audited->recordIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::METADATA,
            severity: IssueSeverity::ERROR,
            code: 'title_missing',
            message: 'No <title>.',
        ));
        $auditedRepo->save($audited);

        $handler = new GetPageDetailHandler(new CrawlingPageDetailReader($pageRepo), $auditedRepo);
        $response = $handler(new GetPageDetailQuery($page->id()->value()));

        $this->assertSame('https://example.com/p', $response->url);
        $this->assertSame(
            ['title_missing'],
            array_map(static fn ($i) => $i->code, $response->issues),
        );
    }

    public function test_no_findings_when_audited_page_absent(): void
    {
        $auditId = AuditId::generate();
        SqliteSchemaFactory::insertAuditRow($this->pdo, $auditId, 'https://example.com/');

        $page = PageFixture::buildWithIssue(
            auditId: $auditId,
            issue: new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                code: 'x',
                message: 'x',
            ),
            url: 'https://example.com/q',
        );
        $pageRepo = new SqlitePageRepository($this->pdo);
        $pageRepo->save($page);

        $handler = new GetPageDetailHandler(new CrawlingPageDetailReader($pageRepo), new SqliteAuditedPageRepository($this->pdo));
        $response = $handler(new GetPageDetailQuery($page->id()->value()));

        $this->assertSame([], $response->issues);
    }
}
