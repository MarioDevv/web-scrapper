<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\StartAudit;

use SeoSpider\Audit\Domain\Model\Audit\Audit;
use SeoSpider\Audit\Domain\Model\Audit\AuditConfiguration;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Crawling\Domain\Model\DiscoverySource;
use SeoSpider\Crawling\Application\Frontier;
use SeoSpider\Crawling\Domain\Model\Url;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class StartAuditHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private Frontier $frontier,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(StartAuditCommand $command): void
    {
        $seedUrl = Url::fromString($command->seedUrl);

        $audit = Audit::start(
            new AuditId($command->auditId),
            new AuditConfiguration(
                seedUrl: $seedUrl->toString(),
                maxPages: $command->maxPages,
                maxDepth: $command->maxDepth,
                concurrency: $command->concurrency,
                requestDelay: $command->requestDelay,
                respectRobotsTxt: $command->respectRobotsTxt,
                customUserAgent: $command->customUserAgent,
                excludePatterns: $command->excludePatterns,
                includePatterns: $command->includePatterns,
                followExternalLinks: $command->followExternalLinks,
                crawlSubdomains: $command->crawlSubdomains,
                crawlResources: $command->crawlResources,
            ),
        );

        $this->auditRepository->save($audit);
        $_ = $this->frontier->enqueue($audit->id()->value(), $seedUrl, depth: 0, source: DiscoverySource::SEED);

        // Count the seed URL as discovered
        $audit->registerUrlsDiscovered(1);
        $this->auditRepository->save($audit);

        $this->eventBus->publish(...$audit->pullDomainEvents());
    }
}