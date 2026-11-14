<?php

declare(strict_types=1);

namespace App\Product\Application\Query\ListProducts;

use App\Product\Application\Query\ReadModel\ProductListView;
use App\Product\Application\Query\ReadModel\ProductView;
use App\Product\Domain\Model\ProductRepositoryInterface;

final readonly class ListProductsHandler
{
    public function __construct(
        private ProductRepositoryInterface $repository,
    ) {
    }

    public function handle(ListProductsQuery $query): ProductListView
    {
        return new ProductListView(
            items: array_map(ProductView::fromDomain(...), $this->repository->findSlice($query->offset(), $query->perPage)),
            page: $query->page,
            perPage: $query->perPage,
            total: $this->repository->count(),
        );
    }
}
