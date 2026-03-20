<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Domain\Bus;

use SeoSpider\Shared\Domain\DomainEvent;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
