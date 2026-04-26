<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Url;

interface PageRepository
{
    public function save(Page $page): void;

    public function findById(PageId $id): ?Page;

    public function findByAuditAndUrl(AuditId $auditId, Url $url): ?Page;

    /** @return Page[] */
    public function findByAudit(AuditId $auditId): array;

    public function countByAudit(AuditId $auditId): int;

    public function nextId(): PageId;

    /**
     * Append issues to an already-persisted page without rewriting the
     * other columns. Used by site-wide analyzers that produce extra
     * issues after the per-page pipeline has already run.
     *
     * @param Issue[] $issues
     */
    public function appendIssues(PageId $pageId, array $issues): void;

    /** @return array<string, Fingerprint> */
    public function fingerprintsByAudit(AuditId $auditId): array;
}
