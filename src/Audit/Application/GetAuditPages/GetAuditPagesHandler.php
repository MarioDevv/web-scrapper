<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditPages;

use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;

final readonly class GetAuditPagesHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private PageSummaryReader $reader,
    ) {
    }

    public function __invoke(GetAuditPagesQuery $query): GetAuditPagesResponse
    {
        $auditId = new AuditId($query->auditId);

        if ($this->auditRepository->findById($auditId) === null) {
            throw AuditNotFound::withId($query->auditId);
        }

        // For delta polls (since != null) the table still wants to show
        // "X of Y in this audit", so the total ignores since rather than
        // collapsing to the delta size.
        $total = $query->since !== null
            ? $this->reader->totalForAudit($auditId)
            : $this->reader->count($query);

        return new GetAuditPagesResponse(
            auditId: $query->auditId,
            pages: $this->reader->read($query),
            total: $total,
        );
    }
}
