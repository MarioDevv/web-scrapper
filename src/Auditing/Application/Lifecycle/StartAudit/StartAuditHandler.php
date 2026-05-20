<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Lifecycle\StartAudit;

use SeoSpider\Auditing\Domain\Model\Audit\Audit;
use SeoSpider\Auditing\Domain\Model\Audit\AuditConfiguration;
use SeoSpider\Auditing\Domain\Model\Audit\AuditFrontier;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class StartAuditHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private AuditFrontier $frontier,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(StartAuditCommand $command): void
    {
        $audit = Audit::start(
            new AuditId($command->auditId),
            new AuditConfiguration(
                seedUrl: $command->seedUrl,
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

        $audit->registerUrlsDiscovered(1);
        $this->auditRepository->save($audit);

        $this->frontier->enqueueSeed($audit->id()->value(), $command->seedUrl);

        $this->eventBus->publish(...$audit->pullDomainEvents());
    }
}
