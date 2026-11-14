<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus;

use App\Shared\Infrastructure\Bus\SymfonyCommandBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class SymfonyCommandBusTest extends TestCase
{
    public function testDispatchesTheCommandToItsHandler(): void
    {
        $handler = new class {
            /** @var list<object> */
            public array $handled = [];

            public function handle(\stdClass $command): string
            {
                $this->handled[] = $command;

                return 'ignored';
            }
        };
        $bus = new SymfonyCommandBus(new ServiceLocator([\stdClass::class => static fn () => $handler]));
        $command = new \stdClass();

        $bus->dispatch($command);

        self::assertSame([$command], $handler->handled);
    }

    public function testFailsForACommandWithoutHandler(): void
    {
        $bus = new SymfonyCommandBus(new ServiceLocator([]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No handler registered for "stdClass".');

        $bus->dispatch(new \stdClass());
    }

    public function testFailsForAHandlerWithoutHandleMethod(): void
    {
        $bus = new SymfonyCommandBus(new ServiceLocator([\stdClass::class => static fn () => new \ArrayObject()]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no handle() method');

        $bus->dispatch(new \stdClass());
    }

    public function testExceptionsOfTheHandlerPassThrough(): void
    {
        $handler = new class {
            public function handle(\stdClass $command): void
            {
                throw new \DomainException('boom');
            }
        };
        $bus = new SymfonyCommandBus(new ServiceLocator([\stdClass::class => static fn () => $handler]));

        $this->expectException(\DomainException::class);

        $bus->dispatch(new \stdClass());
    }
}
