<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\PauseAudit;

use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class PauseAuditHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(PauseAuditCommand $command): void
    {
        $audit = $this->auditRepository->findById(new AuditId($command->auditId));

        if ($audit === null) {
            throw AuditNotFound::withId($command->auditId);
        }

        $audit->pause();

        $this->auditRepository->save($audit);
        $this->eventBus->publish(...$audit->pullDomainEvents());
    }
}
