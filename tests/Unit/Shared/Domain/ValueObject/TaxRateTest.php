<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\TaxRate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TaxRateTest extends TestCase
{
    #[Test]
    public function itHoldsTheRateInBasisPoints(): void
    {
        self::assertSame(1900, TaxRate::fromBasisPoints(1900)->basisPoints());
    }

    #[Test]
    public function itAllowsZero(): void
    {
        self::assertSame(0, TaxRate::fromBasisPoints(0)->basisPoints());
    }

    #[Test]
    public function itRejectsANegativeRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative, got -1 basis points');

        TaxRate::fromBasisPoints(-1);
    }

    #[Test]
    public function itIsEqualOnlyWithTheSameRate(): void
    {
        $rate = TaxRate::fromBasisPoints(1900);

        self::assertTrue($rate->equals(TaxRate::fromBasisPoints(1900)));
        self::assertFalse($rate->equals(TaxRate::fromBasisPoints(700)));
    }
}
