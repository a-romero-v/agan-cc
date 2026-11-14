<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventPublisher;

use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\Port\DomainEventPublisherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches the event itself to Messenger, without a wrapper message.
 *
 * Every DomainEvent is routed to the `domain_events` transport
 * (config/packages/messenger.yaml). The transport is chosen by
 * MESSENGER_TRANSPORT_DSN: with `doctrine://default` the message is written
 * inside the caller's transaction, which makes it a transactional outbox;
 * with `sync://` (test) the handlers run inline.
 */
final readonly class MessengerDomainEventPublisher implements DomainEventPublisherInterface
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public function publish(DomainEvent $event): void
    {
        $this->messageBus->dispatch($event);
    }
}
