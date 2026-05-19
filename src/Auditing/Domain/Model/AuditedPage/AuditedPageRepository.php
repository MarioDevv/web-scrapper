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

    /**
     * Persists the page's findings. Replaces the page's issue rows so
     * re-analysis is idempotent. No-op if the page row does not exist.
     */
    public function save(AuditedPage $page): void;

    /**
     * Issue codes recorded for every audited page of an audit, keyed by
     * page URL. Used by reporting/diff so it never reads findings through
     * the crawl-side Page.
     *
     * @return array<string, string[]>
     */
    public function issueCodesByUrl(string $auditId): array;
}
