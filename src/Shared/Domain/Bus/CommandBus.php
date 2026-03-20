<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Domain\Bus;

interface CommandBus
{
    public function dispatch(object $command): void;
}
