<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\DiscoverySource;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\FrontierEntry;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;

final class InMemoryFrontier implements Frontier
{
    /** @var array<string, FrontierEntry[]> */
    private array $queues = [];

    /** @var array<string, array<string, true>> */
    private array $known = [];

    /** @var array<string, array<string, DiscoverySource>> */
    private array $sources = [];

    public function __construct(private readonly UrlCanonicalizer $canonicalizer)
    {
    }

    public function enqueue(AuditId $auditId, Url $url, int $depth, DiscoverySource $source): bool
    {
        $key = $auditId->value();
        $canonical = $this->canonicalizer->canonicalize($url);
        $urlString = $canonical->toString();

        if ($this->isKnown($auditId, $canonical)) {
            return false;
        }

        $this->queues[$key][] = new FrontierEntry($canonical, $depth);
        $this->known[$key][$urlString] = true;
        $this->sources[$key][$urlString] = $source;

        return true;
    }

    public function sourceOf(AuditId $auditId, Url $url): ?DiscoverySource
    {
        $urlString = $this->canonicalizer->canonicalize($url)->toString();

        return $this->sources[$auditId->value()][$urlString] ?? null;
    }

    public function dequeue(AuditId $auditId): ?FrontierEntry
    {
        $batch = $this->dequeueBatch($auditId, 1);

        return $batch[0] ?? null;
    }

    public function dequeueBatch(AuditId $auditId, int $count): array
    {
        $key = $auditId->value();

        if ($count < 1 || !isset($this->queues[$key]) || $this->queues[$key] === []) {
            return [];
        }

        return array_splice($this->queues[$key], 0, $count);
    }

    public function markVisited(AuditId $auditId, Url $url): void
    {
        $this->known[$auditId->value()][$this->canonicalizer->canonicalize($url)->toString()] = true;
    }

    public function isKnown(AuditId $auditId, Url $url): bool
    {
        return isset($this->known[$auditId->value()][$this->canonicalizer->canonicalize($url)->toString()]);
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

    public function urlsBySource(AuditId $auditId, DiscoverySource $source): array
    {
        $byUrl = $this->sources[$auditId->value()] ?? [];

        $matching = [];
        foreach ($byUrl as $urlString => $entrySource) {
            if ($entrySource === $source) {
                $matching[] = Url::fromString($urlString);
            }
        }

        return $matching;
    }
}
