<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\AnalyzePage;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\AnalyzePage\AnalyzePageOnPageFetched;
use SeoSpider\Auditing\Application\Lifecycle\StartAudit\StartAuditCommand;
use SeoSpider\Auditing\Application\Lifecycle\StartAudit\StartAuditHandler;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Crawling\Domain\Model\Page\Page;
use SeoSpider\Shared\Integration\PageWasCrawled;
use SeoSpider\Auditing\Domain\Model\Analysis\Analyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\IssueCollector;
use SeoSpider\Auditing\Domain\Model\Analysis\PageSignals;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Url;
use SeoSpider\Crawling\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Auditing\Infrastructure\Acl\FrontierBackedAuditFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;
use SeoSpider\Tests\Auditing\Infrastructure\InMemory\InMemoryAuditedPageRepository;

final class AnalyzePageOnPageFetchedTest extends TestCase
{
    private InMemoryAuditRepository $audits;
    private InMemoryPageRepository $pages;
    private InMemoryAuditedPageRepository $auditedPages;
    private InMemoryEventBus $events;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->audits = new InMemoryAuditRepository();
        $this->pages = new InMemoryPageRepository();
        $this->auditedPages = new InMemoryAuditedPageRepository();
        $this->events = new InMemoryEventBus();

        $start = new StartAuditHandler($this->audits, new FrontierBackedAuditFrontier(new InMemoryFrontier(new UrlCanonicalizer())), $this->events);
        $auditId = AuditId::generate()->value();
        $start(new StartAuditCommand(auditId: $auditId, seedUrl: 'https://example.com'));
        $this->auditId = new AuditId($auditId);
        $this->events->reset();
    }

    public function test_runs_analyzers_saves_page_with_issues_and_updates_audit_stats(): void
    {
        $page = $this->persistPage();

        ($this->buildReactor([
            $this->analyzerThatAppends(IssueSeverity::ERROR),
            $this->analyzerThatAppends(IssueSeverity::WARNING),
        ]))(new PageWasCrawled(
            pageId: $page->id()->value(),
            auditId: $this->auditId->value(),
            url: $page->url()->toString(),
            newUrlsDiscovered: 3,
            occurredAt: new DateTimeImmutable(),
        ));

        $audited = $this->auditedPages->findByAuditAndUrl(
            $this->auditId->value(),
            $page->url()->toString(),
        );
        $this->assertNotNull($audited);
        $this->assertSame(1, $audited->errorCount());
        $this->assertSame(1, $audited->warningCount());

        $audit = $this->audits->findById($this->auditId);
        $this->assertNotNull($audit);
        $this->assertSame(1, $audit->statistics()->pagesCrawled);
        $this->assertSame(1, $audit->statistics()->errorsFound);
        $this->assertSame(1, $audit->statistics()->warningsFound);
        $this->assertSame(4, $audit->statistics()->pagesDiscovered);
    }

    public function test_also_persists_findings_through_the_auditing_repository(): void
    {
        $page = $this->persistPage();

        ($this->buildReactor([$this->analyzerThatAppends(IssueSeverity::ERROR)]))(new PageWasCrawled(
            pageId: $page->id()->value(),
            auditId: $this->auditId->value(),
            url: $page->url()->toString(),
            newUrlsDiscovered: 0,
            occurredAt: new DateTimeImmutable(),
        ));

        $audited = $this->auditedPages->findByAuditAndUrl(
            $this->auditId->value(),
            'https://example.com/page',
        );
        $this->assertNotNull($audited);
        $this->assertSame(
            ['test-code-error'],
            array_map(static fn ($i) => $i->code(), $audited->issues()),
        );
        $this->assertSame(1, $audited->errorCount());
    }

    public function test_is_a_noop_when_the_page_has_been_deleted(): void
    {
        ($this->buildReactor([]))(new PageWasCrawled(
            pageId: \SeoSpider\Crawling\Domain\Model\Page\PageId::generate()->value(),
            auditId: $this->auditId->value(),
            url: "https://example.com/missing",
            newUrlsDiscovered: 0,
            occurredAt: new DateTimeImmutable(),
        ));

        $this->assertSame([], $this->events->published());
        $audit = $this->audits->findById($this->auditId);
        $this->assertNotNull($audit);
        $this->assertSame(0, $audit->statistics()->pagesCrawled);
    }

    /** @param Analyzer[] $analyzers */
    private function buildReactor(array $analyzers): AnalyzePageOnPageFetched
    {
        return new AnalyzePageOnPageFetched(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            eventBus: $this->events,
            auditedPageRepository: $this->auditedPages,
            analyzers: $analyzers,
        );
    }

    private function persistPage(): Page
    {
        $page = Page::fromCrawl(
            id: $this->pages->nextId(),
            auditId: $this->auditId->value(),
            url: Url::fromString('https://example.com/page'),
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: '',
                contentType: 'text/html',
                bodySize: 0,
                responseTime: 10.0,
                finalUrl: null,
            ),
            redirectChain: null,
            crawlDepth: 0,
        );
        $this->pages->save($page);

        return $page;
    }

    private function analyzerThatAppends(IssueSeverity $severity): Analyzer
    {
        return new class ($severity) implements Analyzer {
            public function __construct(private IssueSeverity $severity) {}

            public function analyze(PageSignals $signals, IssueCollector $issues): void
            {
                $issues->add(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::METADATA,
                    severity: $this->severity,
                    code: 'test-code-' . $this->severity->value,
                    message: 'test',
                ));
            }

            public function category(): IssueCategory
            {
                return IssueCategory::METADATA;
            }
        };
    }
}
