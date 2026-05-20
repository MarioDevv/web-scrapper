<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Shared\Infrastructure\Bus;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SeoSpider\Shared\Infrastructure\Bus\SyncCommandBus;

final class SyncCommandBusTest extends TestCase
{
    public function test_resolves_and_invokes_the_registered_handler(): void
    {
        $command = new SyncCommandBusTest_FakeCommand();
        $handler = new SyncCommandBusTest_FakeHandler();
        $container = new Container();
        $container->instance(SyncCommandBusTest_FakeHandler::class, $handler);

        $bus = new SyncCommandBus($container, [
            SyncCommandBusTest_FakeCommand::class => SyncCommandBusTest_FakeHandler::class,
        ]);
        $bus->dispatch($command);

        $this->assertSame($command, $handler->received);
    }

    public function test_throws_when_no_handler_is_registered(): void
    {
        $bus = new SyncCommandBus(new Container(), []);

        $this->expectException(RuntimeException::class);
        $bus->dispatch(new SyncCommandBusTest_FakeCommand());
    }
}

final class SyncCommandBusTest_FakeCommand {}

final class SyncCommandBusTest_FakeHandler
{
    public ?object $received = null;
    public function __invoke(object $c): void
    {
        $this->received = $c;
    }
}
