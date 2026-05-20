<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\SitemapIndex;

final class InMemorySitemapIndex implements SitemapIndex
{
    /** @var array<string, string[]> */
    private array $byAudit = [];

    public function put(string $auditId, string $url): void
    {
        $this->byAudit[$auditId][] = $url;
    }

    public function urlsFor(string $auditId): array
    {
        return $this->byAudit[$auditId] ?? [];
    }
}
