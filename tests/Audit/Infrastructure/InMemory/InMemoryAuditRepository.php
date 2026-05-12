<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\Audit\Audit;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatus;

final class InMemoryAuditRepository implements AuditRepository
{
    /** @var array<string, Audit> */
    private array $audits = [];

    public function save(Audit $audit): void
    {
        $this->audits[$audit->id()->value()] = $audit;
    }

    public function findById(AuditId $id): ?Audit
    {
        return $this->audits[$id->value()] ?? null;
    }

    public function nextId(): AuditId
    {
        return AuditId::generate();
    }

    public function findPreviousCompletedByHost(string $host, AuditId $excluding): ?Audit
    {
        $candidates = [];
        foreach ($this->audits as $audit) {
            if ($audit->id()->value() === $excluding->value()) {
                continue;
            }
            if ($audit->status() !== AuditStatus::COMPLETED) {
                continue;
            }
            if ($audit->configuration()->seedUrl->host() !== $host) {
                continue;
            }
            $completedAt = $audit->statistics()->completedAt;
            if ($completedAt === null) {
                continue;
            }
            $candidates[] = [$audit, $completedAt];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn(array $a, array $b) => $b[1] <=> $a[1]);

        return $candidates[0][0];
    }
}
