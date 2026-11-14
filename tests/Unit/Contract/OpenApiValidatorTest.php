<?php

declare(strict_types=1);

namespace App\Tests\Unit\Contract;

use App\Tests\Shared\ContractViolation;
use App\Tests\Shared\OpenApiValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves that the Behat contract steps really fail on a deviation, using the
 * real docs/openapi.yaml. It also shows that the schemas (allOf, shared
 * responses, formats) are usable by the JSON Schema validator.
 */
final class OpenApiValidatorTest extends TestCase
{
    private const string CART_ID = '019a7f3e-5b1c-7c3a-9d2e-4f6a8b0c1d2e';
    private const string ITEM_ID = '019a7f3e-6c2d-7d4b-8e3f-5a7b9c1d2e3f';
    private const string PRODUCT_ID = '019a7f3e-4a0b-7b29-8c1d-3e5f7a9b0c1d';

    private OpenApiValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new OpenApiValidator(\dirname(__DIR__, 3).'/docs/openapi.yaml');
    }

    public function testAcceptsACartThatMatchesTheContract(): void
    {
        $this->validator->assertResponse('GET', '/carts/'.self::CART_ID, 200, 'application/json', self::json(self::cart()));

        $this->addToAssertionCount(1);
    }

    public function testAcceptsACreatedCartWithAndWithoutToken(): void
    {
        $this->validator->assertResponse('POST', '/carts', 201, 'application/json', self::json(self::cart() + ['token' => 'cart_abc']));
        $this->validator->assertResponse('POST', '/carts', 200, 'application/json', self::json(self::cart()));

        $this->addToAssertionCount(2);
    }

    public function testAcceptsAnEmptyBodyWhereTheContractDocumentsNone(): void
    {
        $this->validator->assertResponse('DELETE', '/carts/'.self::CART_ID.'/items/'.self::ITEM_ID, 204, null, '');

        $this->addToAssertionCount(1);
    }

    public function testAcceptsASharedProblemDetailsResponse(): void
    {
        $problem = ['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Resource not found.', 'errorCode' => 4001];

        $this->validator->assertResponse('GET', '/carts/'.self::CART_ID, 404, 'application/problem+json; charset=utf-8', self::json($problem));

        $this->addToAssertionCount(1);
    }

    /**
     * @param array<string, mixed>|string $body
     */
    #[DataProvider('invalidResponses')]
    public function testRejectsAResponseThatDeviatesFromTheContract(string $method, string $path, int $status, ?string $contentType, array|string $body, string $expectedMessage): void
    {
        $this->expectException(ContractViolation::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->assertResponse($method, $path, $status, $contentType, \is_string($body) ? $body : self::json($body));
    }

    /**
     * @return iterable<string, array{string, string, int, ?string, array<string, mixed>|string, string}>
     */
    public static function invalidResponses(): iterable
    {
        $cartPath = '/carts/'.self::CART_ID;
        $itemPath = $cartPath.'/items/'.self::ITEM_ID;

        $missingReserved = self::cart();
        unset($missingReserved['items'][0]['reserved']);
        yield 'missing required field' => ['GET', $cartPath, 200, 'application/json', $missingReserved, 'reserved'];

        $floatAmount = self::cart();
        $floatAmount['totals']['gross']['amount'] = 29.97;
        yield 'money as float' => ['GET', $cartPath, 200, 'application/json', $floatAmount, 'does not match the OpenAPI spec'];

        $badToken = self::cart() + ['token' => 'eyJhbGciOi'];
        yield 'token without the cart_ prefix' => ['POST', '/carts', 201, 'application/json', $badToken, 'does not match the OpenAPI spec'];

        yield 'problem without errorCode' => ['GET', $cartPath, 404, 'application/problem+json', ['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404], 'errorCode'];
        yield 'undocumented status code' => ['GET', $cartPath, 403, 'application/problem+json', [], 'does not document this status code'];
        yield 'undocumented content type' => ['GET', $cartPath, 404, 'application/json', [], 'content type "application/json" is not documented'];
        yield 'body where none is documented' => ['DELETE', $itemPath, 204, 'application/json', '{}', 'documents no body'];
        yield 'invalid JSON' => ['GET', $cartPath, 200, 'application/json', '<html>', 'not valid JSON'];
        yield 'unknown path' => ['GET', '/orders', 200, 'application/json', [], 'no path matching /orders'];
        yield 'unknown operation' => ['PUT', $cartPath, 200, 'application/json', [], 'no operation PUT'];
    }

    public function testAcceptsARequestBodyThatMatchesTheContract(): void
    {
        $path = '/carts/'.self::CART_ID.'/items';

        self::assertTrue($this->validator->hasRequestBodySchema('POST', $path));
        self::assertFalse($this->validator->hasRequestBodySchema('GET', '/carts/'.self::CART_ID));
        $this->validator->assertRequestBody('POST', $path, self::json(['productId' => self::PRODUCT_ID, 'quantity' => 2]));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidRequestBodies')]
    public function testRejectsARequestBodyThatDeviatesFromTheContract(array $body): void
    {
        $this->expectException(ContractViolation::class);

        $this->validator->assertRequestBody('POST', '/carts/'.self::CART_ID.'/items', self::json($body));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidRequestBodies(): iterable
    {
        yield 'quantity below the minimum' => [['productId' => self::PRODUCT_ID, 'quantity' => 0]];
        yield 'quantity above the maximum' => [['productId' => self::PRODUCT_ID, 'quantity' => 100]];
        yield 'missing productId' => [['quantity' => 1]];
        yield 'productId is not a UUID' => [['productId' => 'abc', 'quantity' => 1]];
    }

    public function testRejectsARequestBodyWhereTheContractDefinesNone(): void
    {
        $this->expectException(ContractViolation::class);
        $this->expectExceptionMessage('defines no application/json request body');

        $this->validator->assertRequestBody('GET', '/carts/'.self::CART_ID, '{}');
    }

    /**
     * The AC4 example: 3 × 9.99 € at 19 %.
     *
     * @return array<string, mixed>
     */
    private static function cart(): array
    {
        $breakdown = ['gross' => self::eur(2997), 'net' => self::eur(2518), 'vat' => self::eur(479)];

        return [
            'id' => self::CART_ID,
            'items' => [[
                'id' => self::ITEM_ID,
                'productId' => self::PRODUCT_ID,
                'productName' => 'Espresso Cup',
                'quantity' => 3,
                'unitPrice' => self::eur(999),
                'taxRateBp' => 1900,
                'total' => $breakdown,
                'reserved' => true,
            ]],
            'totals' => $breakdown,
        ];
    }

    /**
     * @return array{amount: int, currency: string}
     */
    private static function eur(int $amount): array
    {
        return ['amount' => $amount, 'currency' => 'EUR'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function json(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR);
    }
}
