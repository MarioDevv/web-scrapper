<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\AuditedPage;

interface AuditedPageRepository
{
    public function findByAuditAndUrl(string $auditId, string $url): ?AuditedPage;

    public function save(AuditedPage $page): void;

    /** @return array<string, string[]> */
    public function issueCodesByUrl(string $auditId): array;
}
