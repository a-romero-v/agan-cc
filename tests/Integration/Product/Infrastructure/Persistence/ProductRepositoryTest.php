<?php

declare(strict_types=1);

namespace App\Tests\Integration\Product\Infrastructure\Persistence;

use App\Product\Domain\Model\Product;
use App\Product\Domain\Model\ProductId;
use App\Product\Domain\Model\ProductRepositoryInterface;
use App\Product\Infrastructure\Persistence\ProductRecord;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The Doctrine adapter against the real test database: mapping, migration and queries.
 */
final class ProductRepositoryTest extends KernelTestCase
{
    private const string CUP = '019a7f3e-4a0b-7b29-8c1d-000000000001';
    private const string FILTERS = '019a7f3e-4a0b-7b29-8c1d-000000000002';
    private const string GRINDER = '019a7f3e-4a0b-7b29-8c1d-000000000003';
    private const string UNKNOWN = '019a7f3e-4a0b-7b29-8c1d-00000000ffff';

    private EntityManagerInterface $em;
    private ProductRepositoryInterface $repository;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(ProductRepositoryInterface::class);

        $this->em->getConnection()->executeStatement('DELETE FROM product');
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM product');

        parent::tearDown();
    }

    #[Test]
    public function itFindsAProductByItsId(): void
    {
        $this->store(self::CUP, 'Espresso Cup', 999);
        $this->store(self::FILTERS, 'Coffee Filter Papers', 100);

        $product = $this->repository->findById(ProductId::fromString(self::CUP));

        self::assertNotNull($product);
        self::assertSame(self::CUP, $product->id()->value());
        self::assertSame('Espresso Cup', $product->name());
        self::assertSame(999, $product->price()->amount());
        self::assertSame('EUR', $product->price()->currency());
        self::assertSame(1900, $product->taxRate()->basisPoints());
    }

    #[Test]
    public function itReturnsNullForAnUnknownId(): void
    {
        $this->store(self::CUP, 'Espresso Cup', 999);

        self::assertNull($this->repository->findById(ProductId::fromString(self::UNKNOWN)));
    }

    #[Test]
    public function itReturnsASliceOfTheCatalogueOrderedByName(): void
    {
        $this->store(self::GRINDER, 'Grinder', 4999);
        $this->store(self::CUP, 'Espresso Cup', 999);
        $this->store(self::FILTERS, 'Coffee Filter Papers', 100);

        self::assertSame(['Coffee Filter Papers', 'Espresso Cup'], $this->names($this->repository->findSlice(0, 2)));
        self::assertSame(['Grinder'], $this->names($this->repository->findSlice(2, 2)));
        self::assertSame([], $this->repository->findSlice(4, 2));
    }

    #[Test]
    public function productsWithTheSameNameKeepAStableOrderAcrossSlices(): void
    {
        $this->store(self::GRINDER, 'Espresso Cup', 999);
        $this->store(self::CUP, 'Espresso Cup', 999);
        $this->store(self::FILTERS, 'Espresso Cup', 999);

        $ids = [];
        foreach ([0, 1, 2] as $offset) {
            foreach ($this->repository->findSlice($offset, 1) as $product) {
                $ids[] = $product->id()->value();
            }
        }

        self::assertSame([self::CUP, self::FILTERS, self::GRINDER], $ids);
    }

    #[Test]
    public function itCountsTheWholeCatalogue(): void
    {
        self::assertSame(0, $this->repository->count());

        $this->store(self::CUP, 'Espresso Cup', 999);
        $this->store(self::FILTERS, 'Coffee Filter Papers', 100);

        self::assertSame(2, $this->repository->count());
    }

    /**
     * @param list<Product> $products
     *
     * @return list<string>
     */
    private function names(array $products): array
    {
        return array_map(static fn (Product $product): string => $product->name(), $products);
    }

    private function store(string $id, string $name, int $priceAmount): void
    {
        $record = new ProductRecord();
        $record->id = $id;
        $record->name = $name;
        $record->priceAmount = $priceAmount;
        $record->priceCurrency = 'EUR';
        $record->taxRateBp = 1900;

        $this->em->persist($record);
        $this->em->flush();
        $this->em->clear();
    }
}
