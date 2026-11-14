<?php

declare(strict_types=1);

namespace App\Product\Domain\Model;

/**
 * Read access to the catalogue. There is no save(): the catalogue is not
 * managed through the application (SPEC, "Out of scope").
 */
interface ProductRepositoryInterface
{
    public function findById(ProductId $id): ?Product;

    /**
     * @return list<Product> the whole catalogue, ordered by name
     */
    public function findAll(): array;
}
