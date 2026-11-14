<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Application\Query\ListProducts;

use App\Product\Application\Query\ListProducts\ListProductsQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ListProductsQueryTest extends TestCase
{
    #[Test]
    public function itDefaultsToTheFirstPage(): void
    {
        $query = new ListProductsQuery();

        self::assertSame(1, $query->page);
        self::assertSame(ListProductsQuery::DEFAULT_PER_PAGE, $query->perPage);
        self::assertSame(0, $query->offset());
    }

    #[Test]
    #[TestWith([1, 20, 0])]
    #[TestWith([2, 20, 20])]
    #[TestWith([4, 5, 15])]
    #[TestWith([1, 100, 0])]
    public function itTurnsThePageIntoAnOffset(int $page, int $perPage, int $offset): void
    {
        self::assertSame($offset, new ListProductsQuery($page, $perPage)->offset());
    }

    #[Test]
    #[TestWith([0, 20])]
    #[TestWith([-1, 20])]
    #[TestWith([1, 0])]
    #[TestWith([1, 101])]
    public function itRejectsAPageOutsideTheLimits(int $page, int $perPage): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ListProductsQuery($page, $perPage);
    }
}
