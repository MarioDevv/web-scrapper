<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\StartAudit;

use SeoSpider\Audit\Domain\Model\Audit\Audit;
use SeoSpider\Audit\Domain\Model\Audit\AuditConfiguration;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class StartAuditHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private Frontier $frontier,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(StartAuditCommand $command): StartAuditResponse
    {
        $seedUrl = Url::fromString($command->seedUrl);

        $audit = Audit::start(
            $this->auditRepository->nextId(),
            new AuditConfiguration(
                seedUrl: $seedUrl,
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
            ),
        );

        $this->auditRepository->save($audit);
        $this->frontier->enqueue($audit->id(), $seedUrl, depth: 0);
        $this->eventBus->publish(...$audit->pullDomainEvents());

        return new StartAuditResponse(
            auditId: $audit->id()->value(),
            seedUrl: $seedUrl->toString(),
            status: $audit->status()->value,
        );
    }
}
