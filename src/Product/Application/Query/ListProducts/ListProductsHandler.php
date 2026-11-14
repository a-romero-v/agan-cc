<?php

declare(strict_types=1);

namespace App\Product\Application\Query\ListProducts;

use App\Product\Application\Query\ReadModel\ProductView;
use App\Product\Domain\Model\ProductRepositoryInterface;

final readonly class ListProductsHandler
{
    public function __construct(
        private ProductRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<ProductView> the whole catalogue, ordered by name
     */
    public function handle(ListProductsQuery $query): array
    {
        return array_map(ProductView::fromDomain(...), $this->repository->findAll());
    }
}
