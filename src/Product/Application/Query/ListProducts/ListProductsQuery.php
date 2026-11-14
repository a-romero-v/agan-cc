<?php

declare(strict_types=1);

namespace App\Product\Application\Query\ListProducts;

/**
 * One page of the catalogue. The limits are checked at the HTTP boundary first;
 * a query outside them is a defect of the caller, not a client error.
 */
final readonly class ListProductsQuery
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 100;

    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('The page starts at 1.');
        }

        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new \InvalidArgumentException(\sprintf('The page size must be between 1 and %d.', self::MAX_PER_PAGE));
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
