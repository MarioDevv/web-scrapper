<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\SiteIssue;
use SeoSpider\Audit\Domain\Model\Page\SiteIssueRepository;

final class InMemorySiteIssueRepository implements SiteIssueRepository
{
    /** @var array<string, SiteIssue[]> */
    private array $byAudit = [];

    /** @param SiteIssue[] $issues */
    public function appendIssues(AuditId $auditId, array $issues): void
    {
        $key = $auditId->value();
        $this->byAudit[$key] = array_merge($this->byAudit[$key] ?? [], $issues);
    }

    /** @return SiteIssue[] */
    public function findByAudit(AuditId $auditId): array
    {
        return $this->byAudit[$auditId->value()] ?? [];
    }
}
