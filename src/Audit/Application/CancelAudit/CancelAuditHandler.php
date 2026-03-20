<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\CancelAudit;

use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class CancelAuditHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private Frontier $frontier,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(CancelAuditCommand $command): void
    {
        $auditId = new AuditId($command->auditId);
        $audit = $this->auditRepository->findById($auditId);

        if ($audit === null) {
            throw AuditNotFound::withId($command->auditId);
        }

        $audit->cancel();
        $this->frontier->clear($auditId);

        $this->auditRepository->save($audit);
        $this->eventBus->publish(...$audit->pullDomainEvents());
    }
}
