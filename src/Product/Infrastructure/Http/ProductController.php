<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Http;

use App\Product\Application\Query\GetProduct\GetProductQuery;
use App\Product\Application\Query\ListProducts\ListProductsQuery;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
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

    // Without a query string the resolver passes nothing, so the default is the first page.
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListProductsRequest $request = new ListProductsRequest(),
    ): JsonResponse {
        return new JsonResponse($this->queryBus->ask(new ListProductsQuery($request->page, $request->perPage)));
    }

    // Anything but a UUID never reaches the controller: the router answers 404.
    #[Route('/{productId}', requirements: ['productId' => Requirement::UUID], methods: ['GET'])]
    public function get(string $productId): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetProductQuery($productId)));
    }
}
