<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model;

use SeoSpider\Crawling\Domain\Model\DiscoverySource;
use SeoSpider\Crawling\Domain\Model\FrontierEntry;
use SeoSpider\Crawling\Domain\Model\Url;

interface Frontier
{
    #[\NoDiscard('Check whether the URL was actually enqueued')]
    public function enqueue(string $auditId, Url $url, int $depth, DiscoverySource $source): bool;

    public function dequeue(string $auditId): ?FrontierEntry;

    /** @return FrontierEntry[] */
    public function dequeueBatch(string $auditId, int $count): array;

    public function markVisited(string $auditId, Url $url): void;

    public function isKnown(string $auditId, Url $url): bool;

    public function isEmpty(string $auditId): bool;

    public function clear(string $auditId): void;

    public function pendingCount(string $auditId): int;

    /** @return Url[] */
    public function urlsBySource(string $auditId, DiscoverySource $source): array;
}
