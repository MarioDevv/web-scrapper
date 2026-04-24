<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Domain\Bus;

use SeoSpider\Shared\Domain\DomainEvent;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;

    /**
     * @param class-string<DomainEvent>     $eventClass
     * @param callable(DomainEvent): void $listener
     */
    public function subscribe(string $eventClass, callable $listener): void;
}
