<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\FrontierEntry;
use SeoSpider\Audit\Domain\Model\Url;

final class InMemoryFrontier implements Frontier
{
    /** @var array<string, FrontierEntry[]> */
    private array $queues = [];

    /** @var array<string, array<string, true>> */
    private array $known = [];

    public function enqueue(AuditId $auditId, Url $url, int $depth): bool
    {
        $key = $auditId->value();
        $normalized = $url->normalized();
        $urlString = $normalized->toString();

        if ($this->isKnown($auditId, $normalized)) {
            return false;
        }

        $this->queues[$key][] = new FrontierEntry($normalized, $depth);
        $this->known[$key][$urlString] = true;

        return true;
    }

    public function dequeue(AuditId $auditId): ?FrontierEntry
    {
        $key = $auditId->value();

        if (!isset($this->queues[$key]) || $this->queues[$key] === []) {
            return null;
        }

        return array_shift($this->queues[$key]);
    }

    public function markVisited(AuditId $auditId, Url $url): void
    {
        $this->known[$auditId->value()][$url->normalized()->toString()] = true;
    }

    public function isKnown(AuditId $auditId, Url $url): bool
    {
        return isset($this->known[$auditId->value()][$url->normalized()->toString()]);
    }

    public function isEmpty(AuditId $auditId): bool
    {
        return !isset($this->queues[$auditId->value()])
            || $this->queues[$auditId->value()] === [];
    }

    public function clear(AuditId $auditId): void
    {
        $key = $auditId->value();
        unset($this->queues[$key], $this->known[$key]);
    }

    public function pendingCount(AuditId $auditId): int
    {
        return count($this->queues[$auditId->value()] ?? []);
    }
}
