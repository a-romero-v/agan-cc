<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Application\Query\ListProducts;

use App\Product\Application\Query\ListProducts\ListProductsHandler;
use App\Product\Application\Query\ListProducts\ListProductsQuery;
use App\Product\Application\Query\ReadModel\ProductView;
use App\Tests\Shared\Fixture\ProductMother;
use App\Tests\Shared\InMemory\InMemoryProductRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListProductsHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsTheCatalogueAsViewsOrderedByName(): void
    {
        $handler = new ListProductsHandler(new InMemoryProductRepository(
            ProductMother::espressoCup(),
            ProductMother::coffeeFilterPapers(),
        ));

        $views = $handler->handle(new ListProductsQuery());

        self::assertContainsOnlyInstancesOf(ProductView::class, $views);
        self::assertSame(
            [
                ['id' => ProductMother::FILTERS_ID, 'name' => 'Coffee Filter Papers', 'price' => ['amount' => 100, 'currency' => 'EUR'], 'taxRateBp' => 1900],
                ['id' => ProductMother::CUP_ID, 'name' => 'Espresso Cup', 'price' => ['amount' => 999, 'currency' => 'EUR'], 'taxRateBp' => 1900],
            ],
            json_decode(json_encode($views, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function itReturnsAnEmptyListForAnEmptyCatalogue(): void
    {
        self::assertSame([], new ListProductsHandler(new InMemoryProductRepository())->handle(new ListProductsQuery()));
    }
}
