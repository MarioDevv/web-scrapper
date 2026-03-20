<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Domain;

abstract class AggregateRoot
{
    /** @var DomainEvent[] */
    private array $domainEvents = [];

    /** @return DomainEvent[] */
    final public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    final protected function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
}
