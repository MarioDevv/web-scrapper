<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Domain\Bus;

interface QueryBus
{
    public function ask(object $query): mixed;
}
