<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Shared\Infrastructure\Bus;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SeoSpider\Shared\Infrastructure\Bus\SyncQueryBus;

final class SyncQueryBusTest extends TestCase
{
    public function test_resolves_and_returns_the_handler_response(): void
    {
        $query = new SyncQueryBusTest_FakeQuery();
        $handler = new SyncQueryBusTest_FakeHandler('answer');
        $container = new Container();
        $container->instance(SyncQueryBusTest_FakeHandler::class, $handler);

        $bus = new SyncQueryBus($container, [
            SyncQueryBusTest_FakeQuery::class => SyncQueryBusTest_FakeHandler::class,
        ]);

        $this->assertSame('answer', $bus->ask($query));
    }

    public function test_throws_when_no_handler_is_registered(): void
    {
        $bus = new SyncQueryBus(new Container(), []);

        $this->expectException(RuntimeException::class);
        $bus->ask(new SyncQueryBusTest_FakeQuery());
    }
}

final class SyncQueryBusTest_FakeQuery {}

final readonly class SyncQueryBusTest_FakeHandler
{
    public function __construct(private string $answer) {}
    public function __invoke(object $q): string
    {
        return $this->answer;
    }
}
