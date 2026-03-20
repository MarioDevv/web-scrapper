<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditStatus;

use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Frontier;

final readonly class GetAuditStatusHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private Frontier $frontier,
    ) {
    }

    public function __invoke(GetAuditStatusQuery $query): GetAuditStatusResponse
    {
        $auditId = new AuditId($query->auditId);
        $audit = $this->auditRepository->findById($auditId);

        if ($audit === null) {
            throw AuditNotFound::withId($query->auditId);
        }

        $stats = $audit->statistics();

        return new GetAuditStatusResponse(
            auditId: $audit->id()->value(),
            seedUrl: $audit->configuration()->seedUrl->toString(),
            status: $audit->status()->value,
            pagesDiscovered: $stats->pagesDiscovered,
            pagesCrawled: $stats->pagesCrawled,
            pagesFailed: $stats->pagesFailed,
            issuesFound: $stats->issuesFound,
            errorsFound: $stats->errorsFound,
            warningsFound: $stats->warningsFound,
            maxPages: $audit->configuration()->maxPages,
            pendingUrls: $this->frontier->pendingCount($auditId),
            startedAt: $stats->startedAt?->format('c'),
            completedAt: $stats->completedAt?->format('c'),
            duration: $stats->duration(),
        );
    }
}
