<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Domain;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
