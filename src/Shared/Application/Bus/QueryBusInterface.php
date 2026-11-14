<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Read side of CQRS.
 *
 * A query reads state without side effects and has exactly one handler.
 * Handlers return read models (`*View`), never entities.
 */
interface QueryBusInterface
{
    public function ask(object $query): mixed;
}
