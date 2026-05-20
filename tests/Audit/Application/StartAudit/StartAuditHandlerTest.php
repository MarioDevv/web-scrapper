<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\StartAudit;

use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Application\Lifecycle\StartAudit\StartAuditCommand;
use SeoSpider\Auditing\Application\Lifecycle\StartAudit\StartAuditHandler;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Audit\AuditStatus;
use SeoSpider\Audit\Domain\Model\Page\InvalidUrl;
use SeoSpider\Crawling\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
use SeoSpider\Audit\Application\Analysis\FrontierBackedAuditFrontier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;

final class StartAuditHandlerTest extends TestCase
{
    private InMemoryAuditRepository $auditRepository;
    private InMemoryFrontier $frontier;
    private InMemoryEventBus $eventBus;
    private StartAuditHandler $handler;

    protected function setUp(): void
    {
        $this->auditRepository = new InMemoryAuditRepository();
        $this->frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $this->eventBus = new InMemoryEventBus();

        $this->handler = new StartAuditHandler(
            $this->auditRepository,
            new FrontierBackedAuditFrontier($this->frontier),
            $this->eventBus,
        );
    }

    public function test_persists_audit(): void
    {
        $auditId = AuditId::generate()->value();

        ($this->handler)(new StartAuditCommand(
            auditId: $auditId,
            seedUrl: 'https://example.com',
        ));

        $audit = $this->auditRepository->findById(new AuditId($auditId));

        $this->assertNotNull($audit);
        $this->assertSame(AuditStatus::RUNNING, $audit->status());
        $this->assertSame(500, $audit->configuration()->maxPages);
    }

    public function test_enqueues_seed_url_in_frontier(): void
    {
        $auditId = AuditId::generate()->value();

        ($this->handler)(new StartAuditCommand(
            auditId: $auditId,
            seedUrl: 'https://example.com',
        ));

        $id = new AuditId($auditId);
        $this->assertSame(1, $this->frontier->pendingCount($id->value()));

        $entry = $this->frontier->dequeue($id->value());
        $this->assertSame('https://example.com/', $entry->url->toString());
        $this->assertSame(0, $entry->depth);
    }

    public function test_publishes_audit_started_event(): void
    {
        ($this->handler)(new StartAuditCommand(
            auditId: AuditId::generate()->value(),
            seedUrl: 'https://example.com',
        ));

        $this->assertCount(1, $this->eventBus->published());
    }

    public function test_respects_custom_configuration(): void
    {
        $auditId = AuditId::generate()->value();

        ($this->handler)(new StartAuditCommand(
            auditId: $auditId,
            seedUrl: 'https://example.com',
            maxPages: 100,
            maxDepth: 5,
            concurrency: 3,
            requestDelay: 0.5,
            respectRobotsTxt: false,
            customUserAgent: 'SeoSpiderBot/1.0',
        ));

        $audit = $this->auditRepository->findById(new AuditId($auditId));
        $config = $audit->configuration();

        $this->assertSame(100, $config->maxPages);
        $this->assertSame(5, $config->maxDepth);
        $this->assertSame(3, $config->concurrency);
        $this->assertSame(0.5, $config->requestDelay);
        $this->assertFalse($config->respectRobotsTxt);
        $this->assertSame('SeoSpiderBot/1.0', $config->customUserAgent);
    }

    public function test_rejects_invalid_seed_url(): void
    {
        $this->expectException(InvalidUrl::class);

        ($this->handler)(new StartAuditCommand(
            auditId: AuditId::generate()->value(),
            seedUrl: 'not a url',
        ));
    }
}
