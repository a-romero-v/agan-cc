<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * An amount in the smallest unit of its currency (cents) plus the currency. Never a float (R4).
 *
 * Shared kernel: Product prices and Cart totals are the same concept, and
 * contexts may not import each other's Domain.
 *
 * Amounts are never negative: a price, a line total and the VAT it includes
 * cannot be. Amounts of different currencies are never combined.
 */
final readonly class Money
{
    private function __construct(
        private int $amount,
        private string $currency,
    ) {
    }

    /**
     * @param int    $amount   in the smallest unit of the currency, not negative
     * @param string $currency ISO 4217 code, three upper-case letters
     */
    public static function of(int $amount, string $currency): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException(\sprintf('A money amount cannot be negative, got %d.', $amount));
        }

        if (1 !== preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new \InvalidArgumentException(\sprintf('A currency is a three-letter ISO 4217 code, got "%s".', $currency));
        }

        return new self($amount, $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new \InvalidArgumentException(\sprintf('Cannot add %s to %s.', $other->currency, $this->currency));
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $other->amount === $this->amount && $other->currency === $this->currency;
    }
}
