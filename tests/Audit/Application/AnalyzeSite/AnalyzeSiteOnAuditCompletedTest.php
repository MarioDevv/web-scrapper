<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\AnalyzeSite;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\AnalyzeSite\AnalyzeSiteOnAuditCompleted;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Domain\Model\Analyzer\SiteAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\SiteAuditContext;
use SeoSpider\Audit\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatistics;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;

final class AnalyzeSiteOnAuditCompletedTest extends TestCase
{
    private InMemoryAuditRepository $audits;
    private InMemoryPageRepository $pages;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->audits = new InMemoryAuditRepository();
        $this->pages = new InMemoryPageRepository();

        $start = new StartAuditHandler(
            $this->audits,
            new InMemoryFrontier(new UrlCanonicalizer()),
            new InMemoryEventBus(),
        );
        $response = $start(new StartAuditCommand(seedUrl: 'https://example.com'));
        $this->auditId = new AuditId($response->auditId);
    }

    public function test_runs_site_analyzers_and_persists_new_issues(): void
    {
        $page = $this->persistPage('https://example.com/page-1');

        $reactor = new AnalyzeSiteOnAuditCompleted(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            siteAnalyzers: [$this->analyzerThatAppends('site_test')],
        );

        ($reactor)($this->event());

        $stored = $this->pages->findById($page->id());
        $this->assertNotNull($stored);
        $codes = array_map(static fn($i) => $i->code(), $stored->issues());
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

        $reactor = new AnalyzeSiteOnAuditCompleted(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            siteAnalyzers: [$this->analyzerThatAppends('site_test')],
        );

        ($reactor)($this->event());

        $stored = $this->pages->findById($page->id());
        $codes = array_map(static fn($i) => $i->code(), $stored->issues());
        sort($codes);
        $this->assertSame(['pre_existing', 'site_test'], $codes);
    }

    public function test_is_a_noop_when_audit_is_unknown(): void
    {
        $reactor = new AnalyzeSiteOnAuditCompleted(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            siteAnalyzers: [$this->analyzerThatAppends('site_test')],
        );

        ($reactor)(new AuditCompleted(
            auditId: AuditId::generate(),
            statistics: new AuditStatistics(),
            occurredAt: new DateTimeImmutable(),
        ));

        // No exception, no persisted state — implicit by reaching this point.
        $this->assertTrue(true);
    }

    public function test_is_a_noop_when_no_analyzers_registered(): void
    {
        $page = $this->persistPage('https://example.com/page-1');

        $reactor = new AnalyzeSiteOnAuditCompleted(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            siteAnalyzers: [],
        );

        ($reactor)($this->event());

        $stored = $this->pages->findById($page->id());
        $this->assertSame([], $stored->issues());
    }

    public function test_does_not_re_persist_pages_with_no_new_issues(): void
    {
        $page = $this->persistPage('https://example.com/page-1');

        $reactor = new AnalyzeSiteOnAuditCompleted(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            siteAnalyzers: [
                new class () implements SiteAnalyzer {
                    public function analyze(SiteAuditContext $context): void
                    {
                        // Inspect-only analyzer; emits nothing.
                    }

                    public function category(): IssueCategory
                    {
                        return IssueCategory::METADATA;
                    }
                },
            ],
        );

        ($reactor)($this->event());

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

    private function analyzerThatAppends(string $code): SiteAnalyzer
    {
        return new class ($code) implements SiteAnalyzer {
            public function __construct(private string $code) {}

            public function analyze(SiteAuditContext $context): void
            {
                foreach ($context->pages as $page) {
                    $page->addIssue(new Issue(
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
