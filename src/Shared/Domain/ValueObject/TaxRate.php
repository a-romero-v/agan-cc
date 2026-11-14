<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * A VAT rate as an integer in basis points: 1 bp = 0.01 %, so 1900 = 19 %. Never a float (R8).
 */
final readonly class TaxRate
{
    private function __construct(
        private int $basisPoints,
    ) {
    }

    public static function fromBasisPoints(int $basisPoints): self
    {
        if ($basisPoints < 0) {
            throw new \InvalidArgumentException(\sprintf('A tax rate cannot be negative, got %d basis points.', $basisPoints));
        }

        return new self($basisPoints);
    }

    public function basisPoints(): int
    {
        return $this->basisPoints;
    }

    public function equals(self $other): bool
    {
        return $other->basisPoints === $this->basisPoints;
    }
}
