<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Infrastructure\Bus;

use Illuminate\Contracts\Container\Container;
use RuntimeException;
use SeoSpider\Shared\Domain\Bus\QueryBus;

final readonly class SyncQueryBus implements QueryBus
{
    /** @param array<class-string, class-string> $handlerMap */
    public function __construct(
        private Container $container,
        private array $handlerMap,
    ) {
    }

    public function ask(object $query): mixed
    {
        $class = $query::class;
        $handlerClass = $this->handlerMap[$class] ?? null;

        if ($handlerClass === null) {
            throw new RuntimeException("No handler registered for query {$class}.");
        }

        $handler = $this->container->make($handlerClass);

        return $handler($query);
    }
}
