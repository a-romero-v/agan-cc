<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Write side of CQRS.
 *
 * A command expresses the intent to change state and has exactly one handler.
 * Commands return nothing: a caller that needs data afterwards asks a query.
 */
interface CommandBusInterface
{
    public function dispatch(object $command): void;
}
