<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reporting\GetAuditStatus;

use SeoSpider\Auditing\Domain\Exception\AuditNotFound;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Auditing\Domain\Model\Reporting\PendingUrlCounter;

final readonly class GetAuditStatusHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private PendingUrlCounter $pendingUrls,
    ) {
    }

    public function __invoke(GetAuditStatusQuery $query): GetAuditStatusResponse
    {
        $audit = $this->auditRepository->findById(new AuditId($query->auditId));

        if ($audit === null) {
            throw AuditNotFound::withId($query->auditId);
        }

        $stats = $audit->statistics();

        return new GetAuditStatusResponse(
            auditId: $audit->id()->value(),
            seedUrl: $audit->configuration()->seedUrl,
            status: $audit->status()->value,
            pagesDiscovered: $stats->pagesDiscovered,
            pagesCrawled: $stats->pagesCrawled,
            pagesFailed: $stats->pagesFailed,
            issuesFound: $stats->issuesFound,
            errorsFound: $stats->errorsFound,
            warningsFound: $stats->warningsFound,
            maxPages: $audit->configuration()->maxPages,
            pendingUrls: $this->pendingUrls->forAudit($audit->id()->value()),
            startedAt: $stats->startedAt?->format('c'),
            completedAt: $stats->completedAt?->format('c'),
            duration: $stats->duration(),
        );
    }
}
