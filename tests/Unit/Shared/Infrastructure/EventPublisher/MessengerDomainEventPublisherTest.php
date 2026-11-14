<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\EventPublisher;

use App\Shared\Infrastructure\EventPublisher\MessengerDomainEventPublisher;
use App\Tests\Shared\Fixture\SampleEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerDomainEventPublisherTest extends TestCase
{
    public function testDispatchesTheEventItselfWithoutAWrapper(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $dispatched = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message, $stamps);
            }
        };
        $event = new SampleEvent('1');

        new MessengerDomainEventPublisher($bus)->publish($event);

        self::assertSame([$event], $bus->dispatched);
    }
}
