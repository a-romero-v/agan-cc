<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    public function itHoldsAnAmountInCentsAndACurrency(): void
    {
        $money = Money::of(999, 'EUR');

        self::assertSame(999, $money->amount());
        self::assertSame('EUR', $money->currency());
    }

    #[Test]
    public function itAllowsZero(): void
    {
        self::assertSame(0, Money::of(0, 'EUR')->amount());
    }

    #[Test]
    public function itRejectsANegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative, got -1');

        Money::of(-1, 'EUR');
    }

    #[Test]
    #[DataProvider('invalidCurrencies')]
    public function itRejectsAnythingButAThreeLetterUpperCaseCurrency(string $currency): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('three-letter ISO 4217 code');

        Money::of(100, $currency);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCurrencies(): iterable
    {
        yield 'empty' => [''];
        yield 'lower case' => ['eur'];
        yield 'too short' => ['EU'];
        yield 'too long' => ['EURO'];
        yield 'symbol' => ['€'];
        yield 'trailing newline' => ["EUR\n"];
    }

    #[Test]
    public function itAddsAmountsOfTheSameCurrencyWithoutChangingTheOperands(): void
    {
        $a = Money::of(2997, 'EUR');
        $b = Money::of(100, 'EUR');

        $sum = $a->add($b);

        self::assertTrue($sum->equals(Money::of(3097, 'EUR')));
        self::assertSame(2997, $a->amount());
        self::assertSame(100, $b->amount());
    }

    #[Test]
    public function itRefusesToAddADifferentCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add USD to EUR.');

        Money::of(100, 'EUR')->add(Money::of(100, 'USD'));
    }

    #[Test]
    public function itIsEqualOnlyWithTheSameAmountAndCurrency(): void
    {
        $money = Money::of(100, 'EUR');

        self::assertTrue($money->equals(Money::of(100, 'EUR')));
        self::assertFalse($money->equals(Money::of(101, 'EUR')));
        self::assertFalse($money->equals(Money::of(100, 'USD')));
    }
}
