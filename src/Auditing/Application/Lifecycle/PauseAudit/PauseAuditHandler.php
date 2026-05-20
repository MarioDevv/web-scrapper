<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Lifecycle\PauseAudit;

use SeoSpider\Auditing\Domain\Exception\AuditNotFound;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
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
