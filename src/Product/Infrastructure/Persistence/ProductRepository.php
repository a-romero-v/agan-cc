<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Persistence;

use App\Product\Domain\Model\Product;
use App\Product\Domain\Model\ProductId;
use App\Product\Domain\Model\ProductRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter of the product repository port.
 */
final readonly class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductMapper $mapper,
    ) {
    }

    public function findById(ProductId $id): ?Product
    {
        $record = $this->em->find(ProductRecord::class, $id->value());

        return null !== $record ? $this->mapper->toDomain($record) : null;
    }

    public function findAll(): array
    {
        // The ID as second criterion keeps the order stable for products with the same name.
        $records = $this->em->getRepository(ProductRecord::class)->findBy([], ['name' => 'ASC', 'id' => 'ASC']);

        return array_map($this->mapper->toDomain(...), $records);
    }
}
