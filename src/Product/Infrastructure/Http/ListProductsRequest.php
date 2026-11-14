<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Http;

use App\Product\Application\Query\ListProducts\ListProductsQuery;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The query string of `GET /products`. Parameters other than these are ignored.
 */
final readonly class ListProductsRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: ListProductsQuery::MAX_PER_PAGE)]
        public int $perPage = ListProductsQuery::DEFAULT_PER_PAGE,
    ) {
    }
}
