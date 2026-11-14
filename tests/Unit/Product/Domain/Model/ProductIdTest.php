<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Domain\Model;

use App\Product\Domain\Model\ProductId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProductIdTest extends TestCase
{
    private const string UUID = '019a7f3e-4a0b-7b29-8c1d-3e5f7a9b0c1d';

    #[Test]
    public function itHoldsACanonicalUuid(): void
    {
        self::assertSame(self::UUID, ProductId::fromString(self::UUID)->value());
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function itRejectsAnythingButACanonicalUuid(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A product ID is a UUID in canonical form');

        ProductId::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'not a UUID' => ['espresso-cup'];
        yield 'upper case' => [strtoupper(self::UUID)];
        yield 'without dashes' => [str_replace('-', '', self::UUID)];
        yield 'trailing newline' => [self::UUID."\n"];
    }

    #[Test]
    public function itIsEqualOnlyWithTheSameValue(): void
    {
        $id = ProductId::fromString(self::UUID);

        self::assertTrue($id->equals(ProductId::fromString(self::UUID)));
        self::assertFalse($id->equals(ProductId::fromString('019a7f3e-4a0b-7b29-8c1d-000000000001')));
    }
}
