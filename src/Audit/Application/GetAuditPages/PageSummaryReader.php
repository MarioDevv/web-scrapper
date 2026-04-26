<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditPages;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;

/**
 * Read port for the audit page list. Implementations bypass the Page
 * aggregate and project flat rows directly from storage so the table
 * never pays for JSON-decoding columns it does not display. The query
 * carries the tab/search/sort/paginate semantics the SpiderDashboard
 * used to apply in PHP.
 */
interface PageSummaryReader
{
    /** @return PageSummary[] */
    public function read(GetAuditPagesQuery $query): array;

    /**
     * Total rows that match the query *ignoring* limit/offset, so the
     * UI can render the right pagination controls without a second
     * round-trip.
     */
    public function count(GetAuditPagesQuery $query): int;

    /**
     * Whole-audit page total ignoring tab/search/since/paging filters.
     * Used so the polling UI can keep saying "X / Y" even when read()
     * only carries the delta of newly crawled pages.
     */
    public function totalForAudit(AuditId $auditId): int;

    /**
     * Counts per UI tab (all, internal, html, redirects, errors,
     * issues, noindex). Computed in a single aggregation query so a
     * dashboard render needs at most two SQL trips: rows + counts.
     *
     * @return array<string, int>
     */
    public function tabCounts(AuditId $auditId): array;
}
