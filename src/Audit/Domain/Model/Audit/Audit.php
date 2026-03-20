<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

use DateTimeImmutable;
use SeoSpider\Shared\Domain\AggregateRoot;

final class Audit extends AggregateRoot
{
    private AuditId $id;
    private AuditConfiguration $configuration;
    private AuditStatus $status;
    private AuditStatistics $statistics;
    private DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function start(AuditId $id, AuditConfiguration $configuration): self
    {
        $audit = new self();
        $audit->id = $id;
        $audit->configuration = $configuration;
        $audit->status = AuditStatus::RUNNING;
        $audit->statistics = new AuditStatistics(
            startedAt: new DateTimeImmutable(),
        );
        $audit->createdAt = new DateTimeImmutable();

        $audit->recordEvent(new AuditStarted(
            $id,
            $configuration->seedUrl,
            new DateTimeImmutable(),
        ));

        return $audit;
    }

    public function registerPageCrawled(int $issueErrors, int $issueWarnings): void
    {
        $this->guardIsRunning('registerPageCrawled');

        $this->statistics = $this->statistics
            ->withPageCrawled()
            ->withIssues($issueErrors, $issueWarnings);

        if ($this->statistics->isLimitReached($this->configuration->maxPages)) {
            $this->complete();
        }
    }

    public function registerPageFailed(): void
    {
        $this->guardIsRunning('registerPageFailed');

        $this->statistics = $this->statistics->withPageFailed();
    }

    public function registerUrlsDiscovered(int $count): void
    {
        $this->guardIsRunning('registerUrlsDiscovered');

        $this->statistics = $this->statistics->withUrlsDiscovered($count);
    }

    public function pause(): void
    {
        $this->guardStatus('pause', AuditStatus::RUNNING);

        $this->status = AuditStatus::PAUSED;
        $this->recordEvent(new AuditPaused($this->id, new DateTimeImmutable()));
    }

    public function resume(): void
    {
        $this->guardStatus('resume', AuditStatus::PAUSED);

        $this->status = AuditStatus::RUNNING;
        $this->recordEvent(new AuditResumed($this->id, new DateTimeImmutable()));
    }

    public function cancel(): void
    {
        $this->guardStatus('cancel', AuditStatus::RUNNING, AuditStatus::PAUSED);

        $this->status = AuditStatus::CANCELLED;
        $this->statistics = $this->statistics->withCompletedAt(new DateTimeImmutable());
        $this->recordEvent(new AuditCancelled($this->id, new DateTimeImmutable()));
    }

    public function complete(): void
    {
        $this->guardStatus('complete', AuditStatus::RUNNING);

        $this->status = AuditStatus::COMPLETED;
        $this->statistics = $this->statistics->withCompletedAt(new DateTimeImmutable());
        $this->recordEvent(new AuditCompleted(
            $this->id,
            $this->statistics,
            new DateTimeImmutable(),
        ));
    }

    public function fail(string $reason): void
    {
        $this->status = AuditStatus::FAILED;
        $this->statistics = $this->statistics->withCompletedAt(new DateTimeImmutable());
        $this->recordEvent(new AuditFailed($this->id, $reason, new DateTimeImmutable()));
    }

    public function id(): AuditId
    {
        return $this->id;
    }

    public function configuration(): AuditConfiguration
    {
        return $this->configuration;
    }

    public function status(): AuditStatus
    {
        return $this->status;
    }

    public function statistics(): AuditStatistics
    {
        return $this->statistics;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isRunning(): bool
    {
        return $this->status === AuditStatus::RUNNING;
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function canAcceptMorePages(): bool
    {
        return $this->isRunning()
            && !$this->statistics->isLimitReached($this->configuration->maxPages);
    }

    private function guardIsRunning(string $action): void
    {
        $this->guardStatus($action, AuditStatus::RUNNING);
    }

    private function guardStatus(string $action, AuditStatus ...$allowed): void
    {
        if (!in_array($this->status, $allowed, true)) {
            throw InvalidAuditStateTransition::because($this->status, $action);
        }
    }
}
