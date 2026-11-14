<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\ReadModel;

use App\Shared\Domain\ValueObject\Money;

/**
 * Money as it appears in every read model: `{amount, currency}`, the amount in cents.
 */
final readonly class MoneyView
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {
    }

    public static function fromMoney(Money $money): self
    {
        return new self($money->amount(), $money->currency());
    }
}
