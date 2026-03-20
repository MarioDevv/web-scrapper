<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\Engine;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\CrawlPage\CrawlPageHandler;
use SeoSpider\Audit\Application\Engine\CrawlerEngine;
use SeoSpider\Audit\Application\Engine\CrawlProgress;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Domain\Model\Analyzer\BrokenLinkAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\DirectiveAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\MetaDataAnalyzer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatus;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\LinkRelation;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\StubHttpClient;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\StubHtmlParser;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\StubRobotsPolicy;

final class CrawlerEngineTest extends TestCase
{
    private InMemoryAuditRepository $auditRepository;
    private InMemoryPageRepository $pageRepository;
    private InMemoryFrontier $frontier;
    private InMemoryEventBus $eventBus;
    private StubHttpClient $httpClient;
    private StubHtmlParser $htmlParser;
    private StubRobotsPolicy $robotsPolicy;
    private CrawlerEngine $engine;

    protected function setUp(): void
    {
        $this->auditRepository = new InMemoryAuditRepository();
        $this->pageRepository = new InMemoryPageRepository();
        $this->frontier = new InMemoryFrontier();
        $this->eventBus = new InMemoryEventBus();
        $this->httpClient = new StubHttpClient();
        $this->htmlParser = new StubHtmlParser();
        $this->robotsPolicy = new StubRobotsPolicy();

        $crawlHandler = new CrawlPageHandler(
            auditRepository: $this->auditRepository,
            pageRepository: $this->pageRepository,
            httpClient: $this->httpClient,
            htmlParser: $this->htmlParser,
            frontier: $this->frontier,
            eventBus: $this->eventBus,
            analyzers: [new BrokenLinkAnalyzer(), new MetaDataAnalyzer(), new DirectiveAnalyzer()],
        );

        $this->engine = new CrawlerEngine(
            auditRepository: $this->auditRepository,
            frontier: $this->frontier,
            crawlPageHandler: $crawlHandler,
            robotsPolicy: $this->robotsPolicy,
        );
    }

    private function startAudit(
        int $maxPages = 500,
        int $maxDepth = 10,
        float $requestDelay = 0.0,
        bool $respectRobotsTxt = false,
    ): string {
        $startHandler = new StartAuditHandler(
            $this->auditRepository,
            $this->frontier,
            $this->eventBus,
        );

        $response = $startHandler(new StartAuditCommand(
            seedUrl: 'https://example.com',
            maxPages: $maxPages,
            maxDepth: $maxDepth,
            requestDelay: $requestDelay,
            respectRobotsTxt: $respectRobotsTxt,
        ));

        $this->eventBus->reset();

        return $response->auditId;
    }

    public function test_crawls_seed_url_and_completes(): void
    {
        $auditId = $this->startAudit();

        $this->engine->run($auditId);

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $this->assertSame(AuditStatus::COMPLETED, $audit->status());
        $this->assertSame(1, $audit->statistics()->pagesCrawled);
    }

    public function test_discovers_and_crawls_linked_pages(): void
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

        $this->engine->run($auditId);

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $this->assertSame(AuditStatus::COMPLETED, $audit->status());
        $this->assertSame(3, $audit->statistics()->pagesCrawled);
    }

    public function test_stops_at_max_pages_limit(): void
    {
        $auditId = $this->startAudit(maxPages: 2);

        $this->htmlParser->withLinks([
            new Link(
                Url::fromString('https://example.com/page1'),
                LinkType::ANCHOR,
                'Page 1',
                LinkRelation::FOLLOW,
                true,
            ),
            new Link(
                Url::fromString('https://example.com/page2'),
                LinkType::ANCHOR,
                'Page 2',
                LinkRelation::FOLLOW,
                true,
            ),
            new Link(
                Url::fromString('https://example.com/page3'),
                LinkType::ANCHOR,
                'Page 3',
                LinkRelation::FOLLOW,
                true,
            ),
        ]);

        $this->engine->run($auditId);

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $this->assertSame(AuditStatus::COMPLETED, $audit->status());
        $this->assertSame(2, $audit->statistics()->pagesCrawled);
    }

    public function test_stops_when_audit_is_paused(): void
    {
        $auditId = $this->startAudit(maxPages: 100);

        $this->htmlParser->withLinks([
            new Link(
                Url::fromString('https://example.com/page1'),
                LinkType::ANCHOR,
                'Page 1',
                LinkRelation::FOLLOW,
                true,
            ),
            new Link(
                Url::fromString('https://example.com/page2'),
                LinkType::ANCHOR,
                'Page 2',
                LinkRelation::FOLLOW,
                true,
            ),
        ]);

        $this->engine->run($auditId, function (CrawlProgress $progress) use ($auditId) {
            if ($progress->pagesCrawled >= 1) {
                $audit = $this->auditRepository->findById(new AuditId($auditId));
                $audit->pause();
                $this->auditRepository->save($audit);
            }
        });

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $this->assertSame(AuditStatus::PAUSED, $audit->status());
        $this->assertLessThan(3, $audit->statistics()->pagesCrawled);
    }

    public function test_skips_urls_disallowed_by_robots(): void
    {
        $auditId = $this->startAudit(respectRobotsTxt: true);

        $this->htmlParser->withLinks([
            new Link(
                Url::fromString('https://example.com/allowed'),
                LinkType::ANCHOR,
                'Allowed',
                LinkRelation::FOLLOW,
                true,
            ),
            new Link(
                Url::fromString('https://example.com/blocked'),
                LinkType::ANCHOR,
                'Blocked',
                LinkRelation::FOLLOW,
                true,
            ),
        ]);

        $this->robotsPolicy->disallow('https://example.com/blocked');

        $this->engine->run($auditId);

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $this->assertSame(2, $audit->statistics()->pagesCrawled);

        $blockedPage = $this->pageRepository->findByAuditAndUrl(
            new AuditId($auditId),
            Url::fromString('https://example.com/blocked'),
        );
        $this->assertNull($blockedPage);
    }

    public function test_reports_progress(): void
    {
        $auditId = $this->startAudit();

        $progressReports = [];
        $this->engine->run($auditId, function (CrawlProgress $progress) use (&$progressReports) {
            $progressReports[] = $progress;
        });

        $this->assertCount(1, $progressReports);
        $this->assertSame($auditId, $progressReports[0]->auditId);
        $this->assertSame('https://example.com', $progressReports[0]->currentUrl);
        $this->assertSame(1, $progressReports[0]->pagesCrawled);
    }

    public function test_handles_http_failures_and_continues(): void
    {
        $auditId = $this->startAudit();

        $this->htmlParser->withLinks([
            new Link(
                Url::fromString('https://example.com/broken'),
                LinkType::ANCHOR,
                'Broken',
                LinkRelation::FOLLOW,
                true,
            ),
            new Link(
                Url::fromString('https://example.com/working'),
                LinkType::ANCHOR,
                'Working',
                LinkRelation::FOLLOW,
                true,
            ),
        ]);

        $this->httpClient->failWith('https://example.com/broken', 'Connection refused');

        $this->engine->run($auditId);

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $this->assertSame(AuditStatus::COMPLETED, $audit->status());
        $this->assertSame(2, $audit->statistics()->pagesCrawled);
        $this->assertSame(1, $audit->statistics()->pagesFailed);
    }

    public function test_does_nothing_for_nonexistent_audit(): void
    {
        $this->engine->run(AuditId::generate()->value());

        $this->assertEmpty($this->eventBus->published());
    }
}