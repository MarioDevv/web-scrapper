<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Bus;

use SeoSpider\Shared\Domain\Bus\EventBus;
use SeoSpider\Shared\Domain\DomainEvent;

final class SyncEventBus implements EventBus
{
    /** @var array<string, list<callable(DomainEvent): void>> */
    private array $listeners = [];

    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            foreach ($this->listeners[$event::class] ?? [] as $listener) {
                $listener($event);
            }
        }
    }
}