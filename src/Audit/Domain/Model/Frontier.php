<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;

interface Frontier
{
    #[\NoDiscard('Check whether the URL was actually enqueued')]
    public function enqueue(AuditId $auditId, Url $url, int $depth, DiscoverySource $source): bool;

    public function dequeue(AuditId $auditId): ?FrontierEntry;

    /**
     * Atomically pulls up to $count pending entries and transitions them to
     * processing. Used by the concurrent engine to launch a parallel fetch
     * batch without racing between dequeue calls.
     *
     * @return FrontierEntry[]
     */
    public function dequeueBatch(AuditId $auditId, int $count): array;

    public function markVisited(AuditId $auditId, Url $url): void;

    public function isKnown(AuditId $auditId, Url $url): bool;

    public function isEmpty(AuditId $auditId): bool;

    public function clear(AuditId $auditId): void;

    public function pendingCount(AuditId $auditId): int;

    /**
     * Lists every URL the frontier knows for a given discovery source
     * (e.g. sitemap), including pending and visited entries. Site-wide
     * analyzers use this to compare what the sitemap declared against
     * what the crawl actually reached.
     *
     * @return Url[]
     */
    public function urlsBySource(AuditId $auditId, DiscoverySource $source): array;
}
