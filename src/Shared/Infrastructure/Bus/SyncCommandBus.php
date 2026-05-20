<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Infrastructure\Bus;

use Illuminate\Contracts\Container\Container;
use RuntimeException;
use SeoSpider\Shared\Domain\Bus\CommandBus;

final readonly class SyncCommandBus implements CommandBus
{
    /** @param array<class-string, class-string> $handlerMap */
    public function __construct(
        private Container $container,
        private array $handlerMap,
    ) {
    }

    public function dispatch(object $command): void
    {
        $class = $command::class;
        $handlerClass = $this->handlerMap[$class] ?? null;

        if ($handlerClass === null) {
            throw new RuntimeException("No handler registered for command {$class}.");
        }

        $handler = $this->container->make($handlerClass);
        $handler($command);
    }
}
