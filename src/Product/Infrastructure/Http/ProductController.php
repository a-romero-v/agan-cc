<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Http;

use App\Product\Application\Query\GetProduct\GetProductQuery;
use App\Product\Application\Query\ListProducts\ListProductsQuery;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The read-only product catalogue. Both routes are public.
 */
#[AsController]
#[Route('/products')]
final readonly class ProductController
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new ListProductsQuery()));
    }

    // Anything but a UUID never reaches the controller: the router answers 404.
    #[Route('/{productId}', methods: ['GET'], requirements: ['productId' => Requirement::UUID])]
    public function get(string $productId): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetProductQuery($productId)));
    }
}
