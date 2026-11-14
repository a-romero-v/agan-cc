<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Marker interface for domain events: facts that happened in the domain.
 *
 * Events are `final readonly` and carry only scalars and value objects,
 * never entities. They are routed to the `domain_events` transport by this
 * interface.
 */
interface DomainEvent
{
}
