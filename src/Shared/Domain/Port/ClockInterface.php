<?php

declare(strict_types=1);

namespace App\Shared\Domain\Port;

/**
 * The only source of the current time. Domain and Application code never
 * calls `new \DateTimeImmutable()`, so tests can control time.
 */
interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
