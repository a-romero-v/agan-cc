<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Application\Query\GetProduct;

use App\Product\Application\Query\GetProduct\GetProductHandler;
use App\Product\Application\Query\GetProduct\GetProductQuery;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Shared\Domain\Exception\ErrorType;
use App\Tests\Shared\Fixture\ProductMother;
use App\Tests\Shared\InMemory\InMemoryProductRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetProductHandlerTest extends TestCase
{
    private GetProductHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new GetProductHandler(new InMemoryProductRepository(
            ProductMother::espressoCup(),
            ProductMother::coffeeFilterPapers(),
        ));
    }

    #[Test]
    public function itReturnsTheProductAsAView(): void
    {
        $view = $this->handler->handle(new GetProductQuery(ProductMother::CUP_ID));

        self::assertSame(
            ['id' => ProductMother::CUP_ID, 'name' => 'Espresso Cup', 'price' => ['amount' => 999, 'currency' => 'EUR'], 'taxRateBp' => 1900],
            json_decode(json_encode($view, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function itFailsForAnUnknownProduct(): void
    {
        try {
            $this->handler->handle(new GetProductQuery(ProductMother::UNKNOWN_ID));
            self::fail('Expected a ProductNotFoundException.');
        } catch (ProductNotFoundException $e) {
            self::assertSame(2001, $e->errorCode());
            self::assertSame(ErrorType::NotFound, $e->type());
            self::assertSame('Product "'.ProductMother::UNKNOWN_ID.'" not found.', $e->getMessage());
        }
    }
}
