<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Infrastructure\InMemory;

use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPage;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;

final class InMemoryAuditedPageRepository implements AuditedPageRepository
{
    /** @var array<string, AuditedPage> */
    private array $byKey = [];

    public function findByAuditAndUrl(string $auditId, string $url): ?AuditedPage
    {
        return $this->byKey[$auditId . '|' . $url] ?? null;
    }

    public function save(AuditedPage $page): void
    {
        $this->byKey[$page->auditId() . '|' . $page->url()] = $page;
    }
}
