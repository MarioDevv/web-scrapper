<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Shared\Domain\Bus\EventBus;
use SeoSpider\Shared\Domain\DomainEvent;

final class InMemoryEventBus implements EventBus
{
    /** @var DomainEvent[] */
    private array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
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
