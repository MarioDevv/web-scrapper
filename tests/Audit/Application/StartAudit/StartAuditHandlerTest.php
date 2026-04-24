<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\StartAudit;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatus;
use SeoSpider\Audit\Domain\Model\Page\InvalidUrl;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryEventBus;
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
            $this->frontier,
            $this->eventBus,
        );
    }

    public function test_starts_audit_and_returns_response(): void
    {
        $response = ($this->handler)(new StartAuditCommand(
            seedUrl: 'https://example.com',
        ));

        $this->assertNotEmpty($response->auditId);
        $this->assertSame('https://example.com', $response->seedUrl);
        $this->assertSame('running', $response->status);
    }

    public function test_persists_audit(): void
    {
        $response = ($this->handler)(new StartAuditCommand(
            seedUrl: 'https://example.com',
        ));

        $audit = $this->auditRepository->findById(new AuditId($response->auditId));

        $this->assertNotNull($audit);
        $this->assertSame(AuditStatus::RUNNING, $audit->status());
        $this->assertSame(500, $audit->configuration()->maxPages);
    }

    public function test_enqueues_seed_url_in_frontier(): void
    {
        $response = ($this->handler)(new StartAuditCommand(
            seedUrl: 'https://example.com',
        ));

        $auditId = new AuditId($response->auditId);

        $this->assertSame(1, $this->frontier->pendingCount($auditId));

        $entry = $this->frontier->dequeue($auditId);
        $this->assertSame('https://example.com/', $entry->url->toString());
        $this->assertSame(0, $entry->depth);
    }

    public function test_publishes_audit_started_event(): void
    {
        ($this->handler)(new StartAuditCommand(seedUrl: 'https://example.com'));

        $this->assertCount(1, $this->eventBus->published());
    }

    public function test_respects_custom_configuration(): void
    {
        $response = ($this->handler)(new StartAuditCommand(
            seedUrl: 'https://example.com',
            maxPages: 100,
            maxDepth: 5,
            concurrency: 3,
            requestDelay: 0.5,
            respectRobotsTxt: false,
            customUserAgent: 'SeoSpiderBot/1.0',
        ));

        $audit = $this->auditRepository->findById(new AuditId($response->auditId));
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

        ($this->handler)(new StartAuditCommand(seedUrl: 'not a url'));
    }
}
