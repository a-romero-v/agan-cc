<?php

declare(strict_types=1);

namespace App\Product\Domain\Model;

/**
 * Read access to the catalogue. There is no save(): the catalogue is not
 * managed through the application (SPEC, "Out of scope").
 *
 * The catalogue is never read as a whole: a list is always one slice of it.
 */
interface ProductRepositoryInterface
{
    public function findById(ProductId $id): ?Product;

    /**
     * @return list<Product> at most $limit products of the catalogue ordered by name, skipping the first $offset
     */
    public function findSlice(int $offset, int $limit): array;

    public function count(): int;
}
