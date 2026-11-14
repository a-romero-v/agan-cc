<?php

declare(strict_types=1);

namespace App\Product\Application\Query\GetProduct;

use App\Product\Application\Query\ReadModel\ProductView;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Product\Domain\Model\ProductId;
use App\Product\Domain\Model\ProductRepositoryInterface;

final readonly class GetProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $repository,
    ) {
    }

    public function handle(GetProductQuery $query): ProductView
    {
        $product = $this->repository->findById(ProductId::fromString($query->productId));

        if (null === $product) {
            throw new ProductNotFoundException($query->productId);
        }

        return ProductView::fromDomain($product);
    }
}
