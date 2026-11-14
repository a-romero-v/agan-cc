<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Persistence;

use App\Product\Domain\Model\Product;
use App\Product\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TaxRate;

/**
 * Translates a stored record into the domain model.
 * There is no toRecord(): the application never writes a product.
 */
final readonly class ProductMapper
{
    public function toDomain(ProductRecord $record): Product
    {
        return Product::reconstitute(
            id: ProductId::fromString($record->id),
            name: $record->name,
            price: Money::of($record->priceAmount, $record->priceCurrency),
            taxRate: TaxRate::fromBasisPoints($record->taxRateBp),
        );
    }
}
