<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Lifecycle\CancelAudit;

use SeoSpider\Auditing\Application\AuditNotFound;
use SeoSpider\Auditing\Domain\Model\Audit\AuditFrontier;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class CancelAuditHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private AuditFrontier $frontier,
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
        $this->frontier->clear($auditId->value());

        $this->auditRepository->save($audit);
        $this->eventBus->publish(...$audit->pullDomainEvents());
    }
}
