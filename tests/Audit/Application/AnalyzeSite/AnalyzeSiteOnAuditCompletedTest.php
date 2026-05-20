<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\AnalyzeSite;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\AnalyzeSite\AnalyzeSiteOnAuditCompleted;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatistics;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Auditing\Domain\Model\Analysis\SiteAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\SiteContext;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Auditing\Domain\Model\Issue\SiteIssue;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Url;
use SeoSpider\Crawling\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemorySiteIssueRepository;
use SeoSpider\Tests\Auditing\Infrastructure\InMemory\InMemoryAuditedPageRepository;

final class AnalyzeSiteOnAuditCompletedTest extends TestCase
{
    private InMemoryAuditRepository $audits;
    private InMemoryPageRepository $pages;
    private InMemorySiteIssueRepository $siteIssues;
    private InMemoryAuditedPageRepository $auditedPages;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->audits = new InMemoryAuditRepository();
        $this->pages = new InMemoryPageRepository();
        $this->siteIssues = new InMemorySiteIssueRepository();
        $this->auditedPages = new InMemoryAuditedPageRepository();

        $start = new StartAuditHandler(
            $this->audits,
            new InMemoryFrontier(new UrlCanonicalizer()),
            new InMemoryEventBus(),
        );
        $auditId = AuditId::generate()->value();
        $start(new StartAuditCommand(auditId: $auditId, seedUrl: 'https://example.com'));
        $this->auditId = new AuditId($auditId);
    }

    public function test_runs_site_analyzers_and_persists_new_issues(): void
    {
        $page = $this->persistPage('https://example.com/page-1');

        ($this->buildReactor([$this->analyzerThatAppends('site_test')]))($this->event());

        $stored = $this->pages->findById($page->id());
        $this->assertNotNull($stored);
        $codes = array_map(static fn ($i) => $i->code(), $stored->issues());
        $this->assertSame(['site_test'], $codes);
    }

    public function test_preserves_existing_issues(): void
    {
        $page = $this->persistPage('https://example.com/page-1');
        $existing = new Issue(
            id: IssueId::generate(),
            category: IssueCategory::METADATA,
            severity: IssueSeverity::ERROR,
            code: 'pre_existing',
            message: 'pre',
        );
        $page->addIssue($existing);
        $this->pages->save($page);

        ($this->buildReactor([$this->analyzerThatAppends('site_test')]))($this->event());

        $stored = $this->pages->findById($page->id());
        $codes = array_map(static fn ($i) => $i->code(), $stored->issues());
        sort($codes);
        $this->assertSame(['pre_existing', 'site_test'], $codes);
    }

    public function test_is_a_noop_when_audit_is_unknown(): void
    {
        ($this->buildReactor([$this->analyzerThatAppends('site_test')]))(new AuditCompleted(
            auditId: AuditId::generate(),
            statistics: new AuditStatistics(),
            occurredAt: new DateTimeImmutable(),
        ));

        $this->assertTrue(true);
    }

    public function test_is_a_noop_when_no_analyzers_registered(): void
    {
        $page = $this->persistPage('https://example.com/page-1');

        ($this->buildReactor([]))($this->event());

        $stored = $this->pages->findById($page->id());
        $this->assertSame([], $stored->issues());
    }

    public function test_persists_site_issues_emitted_by_analyzers(): void
    {
        $this->persistPage('https://example.com/page-1');

        ($this->buildReactor([
            $this->analyzerThatEmitsSiteIssue('orphan_test', 'https://example.com/orphan'),
        ]))($this->event());

        $stored = $this->siteIssues->findByAudit($this->auditId);
        $this->assertCount(1, $stored);
        $this->assertSame('orphan_test', $stored[0]->code);
        $this->assertSame('https://example.com/orphan', $stored[0]->context);
    }

    public function test_does_not_re_persist_pages_with_no_new_issues(): void
    {
        $page = $this->persistPage('https://example.com/page-1');

        ($this->buildReactor([
            new class () implements SiteAnalyzer {
                public function analyze(SiteContext $context): void
                {
                }

                public function category(): IssueCategory
                {
                    return IssueCategory::METADATA;
                }
            },
        ]))($this->event());

        $stored = $this->pages->findById($page->id());
        $this->assertSame([], $stored->issues());
    }

    private function event(): AuditCompleted
    {
        return new AuditCompleted(
            auditId: $this->auditId,
            statistics: new AuditStatistics(),
            occurredAt: new DateTimeImmutable(),
        );
    }

    /** @param SiteAnalyzer[] $analyzers */
    private function buildReactor(array $analyzers): AnalyzeSiteOnAuditCompleted
    {
        return new AnalyzeSiteOnAuditCompleted(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            siteIssueRepository: $this->siteIssues,
            auditedPageRepository: $this->auditedPages,
            siteAnalyzers: $analyzers,
        );
    }

    private function persistPage(string $url): Page
    {
        $requestUrl = Url::fromString($url);
        $page = Page::fromCrawl(
            id: $this->pages->nextId(),
            auditId: $this->auditId,
            url: $requestUrl,
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: ['content-type' => 'text/html'],
                body: '',
                contentType: 'text/html',
                bodySize: 0,
                responseTime: 0.1,
                finalUrl: $requestUrl,
            ),
            redirectChain: null,
            crawlDepth: 0,
        );
        $this->pages->save($page);

        return $page;
    }

    private function analyzerThatEmitsSiteIssue(string $code, string $context): SiteAnalyzer
    {
        return new class ($code, $context) implements SiteAnalyzer {
            public function __construct(private string $code, private string $context) {}

            public function analyze(SiteContext $auditContext): void
            {
                $auditContext->addSiteIssue(new SiteIssue(
                    id: IssueId::generate(),
                    category: IssueCategory::LINKS,
                    severity: IssueSeverity::NOTICE,
                    code: $this->code,
                    message: 'orphan',
                    context: $this->context,
                ));
            }

            public function category(): IssueCategory
            {
                return IssueCategory::LINKS;
            }
        };
    }

    private function analyzerThatAppends(string $code): SiteAnalyzer
    {
        return new class ($code) implements SiteAnalyzer {
            public function __construct(private string $code) {}

            public function analyze(SiteContext $context): void
            {
                foreach ($context->pages() as $page) {
                    $context->addPageIssue($page->url(), new Issue(
                        id: IssueId::generate(),
                        category: IssueCategory::METADATA,
                        severity: IssueSeverity::NOTICE,
                        code: $this->code,
                        message: 'site analyzer',
                    ));
                }
            }

            public function category(): IssueCategory
            {
                return IssueCategory::METADATA;
            }
        };
    }
}
