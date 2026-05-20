<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Crawling\Domain\Model\Page\Fingerprint;

use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Crawling\Domain\Model\Url;

interface PageRepository
{
    public function save(Page $page): void;

    public function findById(PageId $id): ?Page;

    public function findByAuditAndUrl(AuditId $auditId, Url $url): ?Page;

    /** @return Page[] */
    public function findByAudit(AuditId $auditId): array;

    /**
     * Returns pages crawled strictly after the given ISO-8601 instant.
     * Used by the polling UI to fetch only the new arrivals each tick
     * instead of rehydrating the whole audit. Pass null/empty to fall
     * back to findByAudit.
     *
     * @return Page[]
     */
    public function findByAuditSince(AuditId $auditId, ?string $sinceIso): array;

    public function countByAudit(AuditId $auditId): int;

    public function nextId(): PageId;

    /** @return array<string, Fingerprint> */
    public function fingerprintsByAudit(AuditId $auditId): array;
}
