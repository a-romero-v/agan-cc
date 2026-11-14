<?php

declare(strict_types=1);

namespace App\Product\Application\Query\GetProduct;

final readonly class GetProductQuery
{
    public function __construct(
        public string $productId,
    ) {
    }
}
