<?php

declare(strict_types=1);

namespace App\Product\Application\Query\ReadModel;

/**
 * Read model of one page of the catalogue. Its public properties are the JSON of the API contract (`ProductListView`).
 */
final readonly class ProductListView
{
    /**
     * @param list<ProductView> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {
    }
}
