<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Shared\Domain\Bus\EventBus;
use SeoSpider\Shared\Domain\DomainEvent;

final class InMemoryEventBus implements EventBus
{
    /** @var DomainEvent[] */
    private array $published = [];

    /** @var array<string, list<callable(DomainEvent): void>> */
    private array $listeners = [];

    /** @param callable(DomainEvent): void $listener */
    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;

            foreach ($this->listeners[$event::class] ?? [] as $listener) {
                $listener($event);
            }
        }
    }

    /** @return DomainEvent[] */
    public function published(): array
    {
        return $this->published;
    }

    public function reset(): void
    {
        $this->published = [];
    }
}
