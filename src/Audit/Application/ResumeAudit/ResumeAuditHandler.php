<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\ResumeAudit;

use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Shared\Domain\Bus\EventBus;

final readonly class ResumeAuditHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(ResumeAuditCommand $command): void
    {
        $audit = $this->auditRepository->findById(new AuditId($command->auditId));

        if ($audit === null) {
            throw AuditNotFound::withId($command->auditId);
        }

        $audit->resume();

        $this->auditRepository->save($audit);
        $this->eventBus->publish(...$audit->pullDomainEvents());
    }
}
