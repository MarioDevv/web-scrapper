<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\BuildAuditSnapshot;

use DateTimeImmutable;
use SeoSpider\Audit\Application\AuditOverview\AuditOverviewBuilder;
use SeoSpider\Audit\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Audit\Domain\Model\Audit\AuditSnapshot;
use SeoSpider\Audit\Domain\Model\Audit\AuditSnapshotRepository;

/**
 * Freezes the audit overview at the moment the crawl finishes so the
 * dashboard can serve it as a single SELECT — no aggregation required
 * — when the user opens the audit later. Runs after
 * AnalyzeSiteOnAuditCompleted because the snapshot has to reflect any
 * site-wide issues that reactor produces.
 */
final readonly class BuildAuditSnapshotOnAuditCompleted
{
    public function __construct(
        private AuditOverviewBuilder $builder,
        private AuditSnapshotRepository $repository,
    ) {
    }

    public function __invoke(AuditCompleted $event): void
    {
        $overview = $this->builder->build($event->auditId);

        $this->repository->save(new AuditSnapshot(
            auditId: $event->auditId,
            overview: $overview,
            generatedAt: new DateTimeImmutable(),
        ));
    }
}
