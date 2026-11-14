<?php

declare(strict_types=1);

namespace App\Shared\Domain\Port;

use App\Shared\Domain\DomainEvent;

/**
 * Hands domain events over for delivery to their handlers.
 *
 * Command handlers publish the events an aggregate released after it is
 * saved and before the transaction commits. Delivery is at-least-once, so
 * event handlers must be idempotent.
 */
interface DomainEventPublisherInterface
{
    public function publish(DomainEvent $event): void;
}
