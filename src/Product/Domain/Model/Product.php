<?php

declare(strict_types=1);

namespace App\Product\Domain\Model;

use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TaxRate;

/**
 * A catalogue article: an ID, a name, a gross price and a tax rate. It holds no stock data.
 *
 * The catalogue is read-only (SPEC, "Out of scope"): products come from
 * fixtures, so there is no create() and no behaviour that changes one.
 */
final readonly class Product
{
    private function __construct(
        private ProductId $id,
        private string $name,
        private Money $price,
        private TaxRate $taxRate,
    ) {
    }

    /**
     * Rebuilds a product from stored state.
     */
    public static function reconstitute(ProductId $id, string $name, Money $price, TaxRate $taxRate): self
    {
        return new self($id, $name, $price, $taxRate);
    }

    public function id(): ProductId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * The gross unit price.
     */
    public function price(): Money
    {
        return $this->price;
    }

    public function taxRate(): TaxRate
    {
        return $this->taxRate;
    }
}
