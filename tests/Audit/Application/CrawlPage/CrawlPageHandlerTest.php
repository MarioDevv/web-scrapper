<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\CrawlPage;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\CrawlPage\CrawlPageCommand;
use SeoSpider\Audit\Application\CrawlPage\CrawlPageHandler;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Domain\Model\Analyzer\BrokenLinkAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\MetaDataAnalyzer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatus;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\LinkRelation;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\PageCrawled;
use SeoSpider\Audit\Domain\Model\Page\PageFailed;
use SeoSpider\Audit\Domain\Model\Page\PageMetadata;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\FrontierUrlDiscoverer;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\StubHttpClient;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\StubHtmlParser;

final class CrawlPageHandlerTest extends TestCase
{
    private InMemoryAuditRepository $auditRepository;
    private InMemoryPageRepository $pageRepository;
    private InMemoryFrontier $frontier;
    private InMemoryEventBus $eventBus;
    private StubHttpClient $httpClient;
    private StubHtmlParser $htmlParser;
    private CrawlPageHandler $handler;

    protected function setUp(): void
    {
        $this->auditRepository = new InMemoryAuditRepository();
        $this->pageRepository = new InMemoryPageRepository();
        $this->frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $this->eventBus = new InMemoryEventBus();
        $this->httpClient = new StubHttpClient();
        $this->htmlParser = new StubHtmlParser();

        $this->handler = new CrawlPageHandler(
            auditRepository: $this->auditRepository,
            pageRepository: $this->pageRepository,
            httpClient: $this->httpClient,
            htmlParser: $this->htmlParser,
            urlDiscoverer: new FrontierUrlDiscoverer($this->frontier),
            eventBus: $this->eventBus,
            analyzers: [new BrokenLinkAnalyzer(), new MetaDataAnalyzer()],
        );
    }

    private function startAudit(int $maxPages = 500, int $maxDepth = 10): string
    {
        $startHandler = new StartAuditHandler(
            $this->auditRepository,
            $this->frontier,
            $this->eventBus,
        );

        $response = $startHandler(new StartAuditCommand(
            seedUrl: 'https://example.com',
            maxPages: $maxPages,
            maxDepth: $maxDepth,
        ));

        $this->frontier->dequeue(new AuditId($response->auditId));
        $this->eventBus->reset();

        return $response->auditId;
    }

    public function test_crawls_page_and_persists_it(): void
    {
        $auditId = $this->startAudit();

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $this->assertSame(1, $this->pageRepository->countByAudit(new AuditId($auditId)));
    }

    public function test_updates_audit_statistics(): void
    {
        $auditId = $this->startAudit();

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $this->assertSame(1, $audit->statistics()->pagesCrawled);
    }

    public function test_publishes_page_crawled_event(): void
    {
        $auditId = $this->startAudit();

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $pageCrawledEvents = array_filter(
            $this->eventBus->published(),
            static fn($e) => $e instanceof PageCrawled,
        );
        $this->assertCount(1, $pageCrawledEvents);
    }

    public function test_discovers_internal_links_and_enqueues_them(): void
    {
        $auditId = $this->startAudit();

        $this->htmlParser->withLinks([
            new Link(
                Url::fromString('https://example.com/about'),
                LinkType::ANCHOR,
                'About',
                LinkRelation::FOLLOW,
                true,
            ),
            new Link(
                Url::fromString('https://example.com/contact'),
                LinkType::ANCHOR,
                'Contact',
                LinkRelation::FOLLOW,
                true,
            ),
        ]);

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $id = new AuditId($auditId);
        $this->assertSame(2, $this->frontier->pendingCount($id));
        $this->assertSame(3, $this->auditRepository->findById($id)->statistics()->pagesDiscovered);
    }

    public function test_does_not_enqueue_nofollow_links(): void
    {
        $auditId = $this->startAudit();

        $this->htmlParser->withLinks([
            new Link(
                Url::fromString('https://example.com/private'),
                LinkType::ANCHOR,
                'Private',
                LinkRelation::NOFOLLOW,
                true,
            ),
        ]);

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $this->assertSame(0, $this->frontier->pendingCount(new AuditId($auditId)));
    }

    public function test_does_not_enqueue_beyond_max_depth(): void
    {
        $auditId = $this->startAudit(maxDepth: 2);

        $this->htmlParser->withLinks([
            new Link(
                Url::fromString('https://example.com/deep'),
                LinkType::ANCHOR,
                'Deep',
                LinkRelation::FOLLOW,
                true,
            ),
        ]);

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com/level2',
            depth: 2,
        ));

        $this->assertSame(0, $this->frontier->pendingCount(new AuditId($auditId)));
    }

    public function test_runs_analyzers_and_detects_missing_title(): void
    {
        $auditId = $this->startAudit();

        $this->htmlParser->withMetadata(new PageMetadata(
            title: null,
            metaDescription: null,
            h1s: [],
            h2s: [],
            headingHierarchy: [],
            charset: 'UTF-8',
            viewport: null,
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
            wordCount: 0,
            lang: null,
        ));

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $pages = $this->pageRepository->findByAudit(new AuditId($auditId));
        $page = array_first($pages);

        $this->assertGreaterThan(0, count($page->issues()));

        $codes = array_map(
            static fn($issue) => $issue->code(),
            $page->issues(),
        );
        $this->assertContains('title_missing', $codes);
        $this->assertContains('h1_missing', $codes);
        $this->assertContains('meta_description_missing', $codes);
        $this->assertContains('viewport_missing', $codes);
    }

    public function test_handles_http_failure_gracefully(): void
    {
        $auditId = $this->startAudit();

        $this->httpClient->failWith('https://example.com', 'Connection refused');

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $this->assertSame(1, $audit->statistics()->pagesFailed);
        $this->assertSame(0, $this->pageRepository->countByAudit(new AuditId($auditId)));

        $failedEvents = array_filter(
            $this->eventBus->published(),
            static fn($e) => $e instanceof PageFailed,
        );
        $this->assertCount(1, $failedEvents);
    }

    public function test_skips_crawl_if_audit_cannot_accept_more_pages(): void
    {
        $auditId = $this->startAudit(maxPages: 1);

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $this->eventBus->reset();

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com/second',
            depth: 1,
        ));

        $this->assertSame(1, $this->pageRepository->countByAudit(new AuditId($auditId)));
        $this->assertEmpty($this->eventBus->published());
    }

    public function test_auto_completes_audit_when_limit_reached(): void
    {
        $auditId = $this->startAudit(maxPages: 1);

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $this->assertSame(AuditStatus::COMPLETED, $audit->status());
    }

    public function test_generates_fingerprint_for_html_pages(): void
    {
        $auditId = $this->startAudit();

        $this->htmlParser->withCleanContent('This is the main content of the page');

        ($this->handler)(new CrawlPageCommand(
            auditId: $auditId,
            url: 'https://example.com',
            depth: 0,
        ));

        $pages = $this->pageRepository->findByAudit(new AuditId($auditId));
        $this->assertNotNull(array_first($pages)->fingerprint());
    }

    public function test_skips_if_audit_not_found(): void
    {
        ($this->handler)(new CrawlPageCommand(
            auditId: AuditId::generate()->value(),
            url: 'https://example.com',
            depth: 0,
        ));

        $this->assertEmpty($this->eventBus->published());
    }
}
