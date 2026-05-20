<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;


use SeoSpider\Crawling\Domain\Model\DiscoverySource;
use SeoSpider\Crawling\Domain\Model\Frontier;
use SeoSpider\Crawling\Domain\Model\FrontierEntry;
use SeoSpider\Crawling\Domain\Model\Url;
use SeoSpider\Crawling\Domain\Model\UrlCanonicalizer;

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

    public function enqueue(string $auditId, Url $url, int $depth, DiscoverySource $source): bool
    {
        $key = $auditId;
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

    public function sourceOf(string $auditId, Url $url): ?DiscoverySource
    {
        $urlString = $this->canonicalizer->canonicalize($url)->toString();

        return $this->sources[$auditId][$urlString] ?? null;
    }

    public function dequeue(string $auditId): ?FrontierEntry
    {
        $batch = $this->dequeueBatch($auditId, 1);

        return $batch[0] ?? null;
    }

    public function dequeueBatch(string $auditId, int $count): array
    {
        $key = $auditId;

        if ($count < 1 || !isset($this->queues[$key]) || $this->queues[$key] === []) {
            return [];
        }

        return array_splice($this->queues[$key], 0, $count);
    }

    public function markVisited(string $auditId, Url $url): void
    {
        $this->known[$auditId][$this->canonicalizer->canonicalize($url)->toString()] = true;
    }

    public function isKnown(string $auditId, Url $url): bool
    {
        return isset($this->known[$auditId][$this->canonicalizer->canonicalize($url)->toString()]);
    }

    public function isEmpty(string $auditId): bool
    {
        return !isset($this->queues[$auditId])
            || $this->queues[$auditId] === [];
    }

    public function clear(string $auditId): void
    {
        $key = $auditId;
        unset($this->queues[$key], $this->known[$key]);
    }

    public function pendingCount(string $auditId): int
    {
        return count($this->queues[$auditId] ?? []);
    }

    public function urlsBySource(string $auditId, DiscoverySource $source): array
    {
        $byUrl = $this->sources[$auditId] ?? [];

        $matching = [];
        foreach ($byUrl as $urlString => $entrySource) {
            if ($entrySource === $source) {
                $matching[] = Url::fromString($urlString);
            }
        }

        return $matching;
    }
}
