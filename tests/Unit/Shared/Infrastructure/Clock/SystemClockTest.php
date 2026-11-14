<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Clock;

use App\Shared\Infrastructure\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testReturnsTheCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $now = new SystemClock()->now();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }
}
