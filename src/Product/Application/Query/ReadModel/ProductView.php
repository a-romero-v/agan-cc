<?php

declare(strict_types=1);

namespace App\Product\Application\Query\ReadModel;

use App\Product\Domain\Model\Product;
use App\Shared\Application\Query\ReadModel\MoneyView;

/**
 * Read model of a product. Its public properties are the JSON of the API contract (`ProductView`).
 */
final readonly class ProductView
{
    public function __construct(
        public string $id,
        public string $name,
        public MoneyView $price,
        public int $taxRateBp,
    ) {
    }

    public static function fromDomain(Product $product): self
    {
        return new self(
            id: $product->id()->value(),
            name: $product->name(),
            price: MoneyView::fromMoney($product->price()),
            taxRateBp: $product->taxRate()->basisPoints(),
        );
    }
}
