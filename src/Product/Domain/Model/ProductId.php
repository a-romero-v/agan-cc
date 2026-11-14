<?php

declare(strict_types=1);

namespace App\Product\Domain\Model;

/**
 * The identity of a product: a UUID in its canonical form (lower case, with dashes).
 */
final readonly class ProductId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value)) {
            throw new \InvalidArgumentException(\sprintf('A product ID is a UUID in canonical form, got "%s".', $value));
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }
}
