<?php

declare(strict_types=1);

namespace App\Tests\Shared\InMemory;

use App\Product\Domain\Model\Product;
use App\Product\Domain\Model\ProductId;
use App\Product\Domain\Model\ProductRepositoryInterface;

/**
 * The product repository port for unit tests: same contract as the Doctrine adapter, no database.
 */
final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var list<Product> */
    private readonly array $products;

    public function __construct(Product ...$products)
    {
        $this->products = array_values($products);
    }

    public function findById(ProductId $id): ?Product
    {
        return array_find($this->products, static fn (Product $product): bool => $product->id()->equals($id));
    }

    public function findAll(): array
    {
        $products = $this->products;
        usort($products, static fn (Product $a, Product $b): int => [$a->name(), $a->id()->value()] <=> [$b->name(), $b->id()->value()]);

        return $products;
    }
}
