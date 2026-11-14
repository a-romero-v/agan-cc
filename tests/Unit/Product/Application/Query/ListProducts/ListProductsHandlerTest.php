<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Application\Query\ListProducts;

use App\Product\Application\Query\ListProducts\ListProductsHandler;
use App\Product\Application\Query\ListProducts\ListProductsQuery;
use App\Product\Application\Query\ReadModel\ProductListView;
use App\Tests\Shared\Fixture\ProductMother;
use App\Tests\Shared\InMemory\InMemoryProductRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListProductsHandlerTest extends TestCase
{
    private const string GRINDER_ID = '019a7f3e-4a0b-7b29-8c1d-000000000003';

    #[Test]
    public function itReturnsAPageOfTheCatalogueOrderedByName(): void
    {
        $page = $this->handler()->handle(new ListProductsQuery());

        self::assertSame(
            [
                'items' => [
                    ['id' => ProductMother::FILTERS_ID, 'name' => 'Coffee Filter Papers', 'price' => ['amount' => 100, 'currency' => 'EUR'], 'taxRateBp' => 1900],
                    ['id' => ProductMother::CUP_ID, 'name' => 'Espresso Cup', 'price' => ['amount' => 999, 'currency' => 'EUR'], 'taxRateBp' => 1900],
                    ['id' => self::GRINDER_ID, 'name' => 'Grinder', 'price' => ['amount' => 4999, 'currency' => 'EUR'], 'taxRateBp' => 1900],
                ],
                'page' => 1,
                'perPage' => ListProductsQuery::DEFAULT_PER_PAGE,
                'total' => 3,
            ],
            json_decode(json_encode($page, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function itReturnsOnlyTheRequestedPage(): void
    {
        $page = $this->handler()->handle(new ListProductsQuery(page: 2, perPage: 2));

        self::assertSame(['Grinder'], $this->names($page));
        self::assertSame(2, $page->page);
        self::assertSame(2, $page->perPage);
        self::assertSame(3, $page->total);
    }

    #[Test]
    public function aPageBehindTheLastOneIsEmptyButStillReportsTheTotal(): void
    {
        $page = $this->handler()->handle(new ListProductsQuery(page: 3, perPage: 2));

        self::assertSame([], $page->items);
        self::assertSame(3, $page->total);
    }

    #[Test]
    public function itReturnsAnEmptyPageForAnEmptyCatalogue(): void
    {
        $page = new ListProductsHandler(new InMemoryProductRepository())->handle(new ListProductsQuery());

        self::assertSame([], $page->items);
        self::assertSame(0, $page->total);
    }

    private function handler(): ListProductsHandler
    {
        return new ListProductsHandler(new InMemoryProductRepository(
            ProductMother::product(self::GRINDER_ID, 'Grinder', 4999),
            ProductMother::espressoCup(),
            ProductMother::coffeeFilterPapers(),
        ));
    }

    /**
     * @return list<string>
     */
    private function names(ProductListView $page): array
    {
        return array_map(static fn ($view): string => $view->name, $page->items);
    }
}
