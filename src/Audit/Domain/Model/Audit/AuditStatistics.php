<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

use DateTimeImmutable;

final readonly class AuditStatistics
{
    public function __construct(
        public int                $pagesDiscovered = 0,
        public int                $pagesCrawled = 0,
        public int                $pagesFailed = 0,
        public int                $issuesFound = 0,
        public int                $errorsFound = 0,
        public int                $warningsFound = 0,
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $completedAt = null,
    )
    {
    }

    #[\NoDiscard('AuditStatistics is immutable — use the returned instance')]
    public function withPageCrawled(): self
    {
        return clone($this, ['pagesCrawled' => $this->pagesCrawled + 1]);
    }

    #[\NoDiscard('AuditStatistics is immutable — use the returned instance')]
    public function withPageFailed(): self
    {
        return clone($this, ['pagesFailed' => $this->pagesFailed + 1]);
    }

    #[\NoDiscard('AuditStatistics is immutable — use the returned instance')]
    public function withUrlsDiscovered(int $count): self
    {
        return clone($this, ['pagesDiscovered' => $this->pagesDiscovered + $count]);
    }

    #[\NoDiscard('AuditStatistics is immutable — use the returned instance')]
    public function withIssues(int $errors, int $warnings): self
    {
        return clone($this,
            [
                'issuesFound' => $this->issuesFound + $errors + $warnings,
                'errorsFound' => $this->errorsFound + $errors,
                'warningsFound' => $this->warningsFound + $warnings,
            ]
        );
    }

    #[\NoDiscard('AuditStatistics is immutable — use the returned instance')]
    public function withStartedAt(DateTimeImmutable $at): self
    {
        return clone($this, ['startedAt' => $at]);
    }

    #[\NoDiscard('AuditStatistics is immutable — use the returned instance')]
    public function withCompletedAt(DateTimeImmutable $at): self
    {
        return clone($this, ['completedAt' => $at]);
    }

    public function isLimitReached(int $maxPages): bool
    {
        return $this->pagesCrawled >= $maxPages;
    }

    public function duration(): ?float
    {
        if ($this->startedAt === null || $this->completedAt === null) {
            return null;
        }

        return (float)$this->completedAt->getTimestamp() - (float)$this->startedAt->getTimestamp();
    }
}
