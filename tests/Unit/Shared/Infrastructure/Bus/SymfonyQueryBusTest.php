<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus;

use App\Shared\Infrastructure\Bus\SymfonyQueryBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class SymfonyQueryBusTest extends TestCase
{
    public function testReturnsTheResultOfTheHandler(): void
    {
        $handler = new class {
            public function handle(\stdClass $query): string
            {
                return 'view of '.$query->id;
            }
        };
        $bus = new SymfonyQueryBus(new ServiceLocator([\stdClass::class => static fn () => $handler]));
        $query = new \stdClass();
        $query->id = '42';

        self::assertSame('view of 42', $bus->ask($query));
    }

    public function testFailsForAQueryWithoutHandler(): void
    {
        $bus = new SymfonyQueryBus(new ServiceLocator([]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No handler registered for "stdClass".');

        $bus->ask(new \stdClass());
    }
}
