<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Infrastructure\Persistence;

use App\Product\Infrastructure\Persistence\ProductMapper;
use App\Product\Infrastructure\Persistence\ProductRecord;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TaxRate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProductMapperTest extends TestCase
{
    #[Test]
    public function itRebuildsTheProductFromItsRecord(): void
    {
        $record = new ProductRecord();
        $record->id = '019a7f3e-4a0b-7b29-8c1d-3e5f7a9b0c1d';
        $record->name = 'Espresso Cup';
        $record->priceAmount = 999;
        $record->priceCurrency = 'EUR';
        $record->taxRateBp = 1900;

        $product = new ProductMapper()->toDomain($record);

        self::assertSame('019a7f3e-4a0b-7b29-8c1d-3e5f7a9b0c1d', $product->id()->value());
        self::assertSame('Espresso Cup', $product->name());
        self::assertTrue($product->price()->equals(Money::of(999, 'EUR')));
        self::assertTrue($product->taxRate()->equals(TaxRate::fromBasisPoints(1900)));
    }
}
