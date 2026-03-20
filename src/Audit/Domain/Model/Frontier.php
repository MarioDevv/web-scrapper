<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;

interface Frontier
{
    #[\NoDiscard('Check whether the URL was actually enqueued')]
    public function enqueue(AuditId $auditId, Url $url, int $depth): bool;

    public function dequeue(AuditId $auditId): ?FrontierEntry;

    public function markVisited(AuditId $auditId, Url $url): void;

    public function isKnown(AuditId $auditId, Url $url): bool;

    public function isEmpty(AuditId $auditId): bool;

    public function clear(AuditId $auditId): void;

    public function pendingCount(AuditId $auditId): int;
}
