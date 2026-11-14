<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\EventPublisher;

use App\Shared\Domain\Port\DomainEventPublisherInterface;
use App\Shared\Infrastructure\EventPublisher\MessengerDomainEventPublisher;
use App\Tests\Shared\Fixture\SampleEvent;
use App\Tests\Shared\Fixture\SampleEventHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Guards the wiring of domain events with the real container:
 * the port resolves to the Messenger adapter, events are routed by the
 * DomainEvent interface, and a pure-PHP handler registered by tag receives them.
 */
final class DomainEventDeliveryTest extends KernelTestCase
{
    public function testThePortIsTheMessengerAdapter(): void
    {
        self::assertInstanceOf(MessengerDomainEventPublisher::class, $this->publisher());
    }

    public function testAPublishedEventReachesTheHandlerRegisteredByTag(): void
    {
        $event = new SampleEvent('42');

        $this->publisher()->publish($event);

        // sync:// in the test environment: the handler has already run.
        $handler = self::getContainer()->get(SampleEventHandler::class);
        self::assertSame([$event], $handler->handled);
    }

    public function testDomainEventsAreRoutedToTheDomainEventsTransport(): void
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);

        $envelope = $bus->dispatch(new SampleEvent('42'));

        $sent = $envelope->last(SentStamp::class);
        self::assertNotNull($sent, 'The event was not sent to a transport: the routing by DomainEvent is missing.');
        self::assertSame('domain_events', $sent->getSenderAlias());

        $handled = $envelope->last(HandledStamp::class);
        self::assertNotNull($handled);
        self::assertStringStartsWith(SampleEventHandler::class, $handled->getHandlerName());
    }

    private function publisher(): DomainEventPublisherInterface
    {
        $publisher = self::getContainer()->get('test.domain_event_publisher');
        self::assertInstanceOf(DomainEventPublisherInterface::class, $publisher);

        return $publisher;
    }
}
