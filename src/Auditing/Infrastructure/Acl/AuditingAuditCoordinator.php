<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Acl;

use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Crawling\Application\AuditCoordinator;
use SeoSpider\Crawling\Domain\Model\Audit\AuditCrawlConfig;
use SeoSpider\Crawling\Domain\Model\Audit\AuditCrawlStats;
use SeoSpider\Crawling\Domain\Model\Audit\AuditSnapshot;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class AuditingAuditCoordinator implements AuditCoordinator
{
    public function __construct(
        private AuditRepository $audits,
        private EventBus $eventBus,
    ) {
    }

    public function snapshot(string $auditId): ?AuditSnapshot
    {
        $audit = $this->audits->findById(new AuditId($auditId));
        if ($audit === null) {
            return null;
        }

        $config = $audit->configuration();
        $stats = $audit->statistics();
        return new AuditSnapshot(
            auditId: $auditId,
            status: $audit->status()->value,
            config: new AuditCrawlConfig(
                seedUrl: $config->seedUrl,
                customUserAgent: $config->customUserAgent,
                respectRobotsTxt: $config->respectRobotsTxt,
                ingestSitemaps: $config->ingestSitemaps,
                concurrency: $config->concurrency,
                requestDelay: $config->requestDelay,
                maxDepth: $config->maxDepth,
                crawlResources: $config->crawlResources,
                maxPages: $config->maxPages,
            ),
            stats: new AuditCrawlStats(
                pagesCrawled: $stats->pagesCrawled,
                pagesFailed: $stats->pagesFailed,
                pagesDiscovered: $stats->pagesDiscovered,
            ),
            canAcceptMorePages: $audit->canAcceptMorePages(),
            isRunning: $audit->isRunning(),
            isFinished: $audit->isFinished(),
        );
    }

    public function registerPageFailed(string $auditId): void
    {
        $audit = $this->audits->findById(new AuditId($auditId));
        if ($audit === null) {
            return;
        }
        $audit->registerPageFailed();
        $this->audits->save($audit);
        $this->eventBus->publish(...$audit->pullDomainEvents());
    }

    public function registerUrlsDiscovered(string $auditId, int $count): void
    {
        if ($count <= 0) {
            return;
        }
        $audit = $this->audits->findById(new AuditId($auditId));
        if ($audit === null) {
            return;
        }
        $audit->registerUrlsDiscovered($count);
        $this->audits->save($audit);
        $this->eventBus->publish(...$audit->pullDomainEvents());
    }

    public function complete(string $auditId): void
    {
        $audit = $this->audits->findById(new AuditId($auditId));
        if ($audit === null) {
            return;
        }
        $audit->complete();
        $this->audits->save($audit);
        $this->eventBus->publish(...$audit->pullDomainEvents());
    }
}
