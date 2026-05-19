<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\AuditedPage;

/**
 * Loads an AuditedPage from the audit's recorded findings. The
 * implementation reads the shared persistence written by the crawl/
 * analysis path; the Auditing context never imports Crawling or the
 * legacy Page — it reconstitutes its own aggregate from primitive rows.
 */
interface AuditedPageRepository
{
    public function findByAuditAndUrl(string $auditId, string $url): ?AuditedPage;
}
