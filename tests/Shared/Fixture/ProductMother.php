<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fixture;

use App\Product\Domain\Model\Product;
use App\Product\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TaxRate;

/**
 * Builds products for unit tests.
 */
final class ProductMother
{
    public const string CUP_ID = '019a7f3e-4a0b-7b29-8c1d-000000000001';
    public const string FILTERS_ID = '019a7f3e-4a0b-7b29-8c1d-000000000002';
    public const string UNKNOWN_ID = '019a7f3e-4a0b-7b29-8c1d-00000000ffff';

    public static function espressoCup(): Product
    {
        return self::product(self::CUP_ID, 'Espresso Cup', 999);
    }

    public static function coffeeFilterPapers(): Product
    {
        return self::product(self::FILTERS_ID, 'Coffee Filter Papers', 100);
    }

    public static function product(string $id, string $name, int $priceAmount, int $taxRateBp = 1900): Product
    {
        return Product::reconstitute(
            ProductId::fromString($id),
            $name,
            Money::of($priceAmount, 'EUR'),
            TaxRate::fromBasisPoints($taxRateBp),
        );
    }
}
