<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\AnalyzePage;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\AnalyzePage\AnalyzePageOnPageFetched;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Domain\Model\Analyzer\Analyzer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageCrawled;
use SeoSpider\Audit\Domain\Model\Page\PageFetched;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;

final class AnalyzePageOnPageFetchedTest extends TestCase
{
    private InMemoryAuditRepository $audits;
    private InMemoryPageRepository $pages;
    private InMemoryEventBus $events;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->audits = new InMemoryAuditRepository();
        $this->pages = new InMemoryPageRepository();
        $this->events = new InMemoryEventBus();

        $start = new StartAuditHandler($this->audits, new InMemoryFrontier(new UrlCanonicalizer()), $this->events);
        $response = $start(new StartAuditCommand(seedUrl: 'https://example.com'));
        $this->auditId = new AuditId($response->auditId);
        $this->events->reset();
    }

    public function test_runs_analyzers_saves_page_with_issues_and_updates_audit_stats(): void
    {
        $page = $this->persistPage();

        $reactor = new AnalyzePageOnPageFetched(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            eventBus: $this->events,
            analyzers: [
                $this->analyzerThatAppends(IssueSeverity::ERROR),
                $this->analyzerThatAppends(IssueSeverity::WARNING),
            ],
        );

        ($reactor)(new PageFetched(
            pageId: $page->id(),
            auditId: $this->auditId,
            newUrlsDiscovered: 3,
            occurredAt: new DateTimeImmutable(),
        ));

        $savedPage = $this->pages->findById($page->id());
        $this->assertNotNull($savedPage);
        $this->assertSame(1, $savedPage->errorCount());
        $this->assertSame(1, $savedPage->warningCount());

        $audit = $this->audits->findById($this->auditId);
        $this->assertNotNull($audit);
        $this->assertSame(1, $audit->statistics()->pagesCrawled);
        $this->assertSame(1, $audit->statistics()->errorsFound);
        $this->assertSame(1, $audit->statistics()->warningsFound);
        // seed (1) + newly discovered (3) = 4
        $this->assertSame(4, $audit->statistics()->pagesDiscovered);
    }

    public function test_publishes_page_crawled_downstream(): void
    {
        $page = $this->persistPage();

        $reactor = new AnalyzePageOnPageFetched(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            eventBus: $this->events,
            analyzers: [],
        );

        ($reactor)(new PageFetched(
            pageId: $page->id(),
            auditId: $this->auditId,
            newUrlsDiscovered: 0,
            occurredAt: new DateTimeImmutable(),
        ));

        $published = array_values(array_filter(
            $this->events->published(),
            static fn($event) => $event instanceof PageCrawled,
        ));

        $this->assertCount(1, $published, 'reactor should publish the PageCrawled event emitted by markAsAnalyzed');
        $this->assertSame($page->id()->value(), $published[0]->pageId->value());
    }

    public function test_is_a_noop_when_the_page_has_been_deleted(): void
    {
        $reactor = new AnalyzePageOnPageFetched(
            pageRepository: $this->pages,
            auditRepository: $this->audits,
            eventBus: $this->events,
            analyzers: [],
        );

        ($reactor)(new PageFetched(
            pageId: \SeoSpider\Audit\Domain\Model\Page\PageId::generate(),
            auditId: $this->auditId,
            newUrlsDiscovered: 0,
            occurredAt: new DateTimeImmutable(),
        ));

        $this->assertSame([], $this->events->published());
        $audit = $this->audits->findById($this->auditId);
        $this->assertNotNull($audit);
        $this->assertSame(0, $audit->statistics()->pagesCrawled);
    }

    private function persistPage(): Page
    {
        $page = Page::fromCrawl(
            id: $this->pages->nextId(),
            auditId: $this->auditId,
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

            public function analyze(Page $page): void
            {
                $page->addIssue(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::METADATA,
                    severity: $this->severity,
                    code: 'test-code',
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
