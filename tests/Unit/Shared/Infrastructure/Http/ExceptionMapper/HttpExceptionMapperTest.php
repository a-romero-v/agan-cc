<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\ExceptionMapper;

use App\Shared\Infrastructure\Http\ExceptionMapper\HttpExceptionMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class HttpExceptionMapperTest extends TestCase
{
    private const string SECRET = 'internal detail that must not leak';

    public function testSupportsOnlyHttpExceptions(): void
    {
        $mapper = new HttpExceptionMapper();

        self::assertTrue($mapper->supports(new NotFoundHttpException()));
        self::assertFalse($mapper->supports(new \RuntimeException('unrelated')));
    }

    #[DataProvider('exceptions')]
    public function testMapsTheStatusToAnErrorCodeAndAFixedDetail(HttpExceptionInterface $exception, int $status, string $title, int $errorCode, string $detail): void
    {
        $response = new HttpExceptionMapper()->toResponse($exception);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame(
            ['type' => 'about:blank', 'title' => $title, 'status' => $status, 'detail' => $detail, 'errorCode' => $errorCode],
            json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return iterable<string, array{HttpExceptionInterface, int, string, int, string}>
     */
    public static function exceptions(): iterable
    {
        yield 'router 404' => [new NotFoundHttpException(self::SECRET), 404, 'Not Found', 4001, 'Resource not found.'];
        yield 'access denied is a 404 (R6)' => [new AccessDeniedHttpException(self::SECRET), 404, 'Not Found', 4001, 'Resource not found.'];
        yield '422 without violations' => [new UnprocessableEntityHttpException(self::SECRET), 422, 'Unprocessable Content', 4002, 'The request is invalid.'];
        yield 'malformed JSON' => [new BadRequestHttpException(self::SECRET), 400, 'Bad Request', 4003, 'Malformed request.'];
        yield '401' => [new UnauthorizedHttpException('Bearer', self::SECRET), 401, 'Unauthorized', 4004, 'Missing or invalid credentials.'];
        yield '405' => [new MethodNotAllowedHttpException(['GET'], self::SECRET), 405, 'Method Not Allowed', 4005, 'Method not allowed.'];
        yield '415' => [new UnsupportedMediaTypeHttpException(self::SECRET), 415, 'Unsupported Media Type', 4006, 'The request body must be application/json.'];
        yield '429' => [new TooManyRequestsHttpException(30, self::SECRET), 429, 'Too Many Requests', 4007, 'Rate limit exceeded.'];
        yield 'other client error' => [new GoneHttpException(self::SECRET), 410, 'Gone', 4003, 'Malformed request.'];
        yield 'server error' => [new ServiceUnavailableHttpException(null, self::SECRET), 503, 'Service Unavailable', 4000, 'Internal error.'];
    }

    public function testAccessDeniedIsIdenticalToAnUnknownResource(): void
    {
        $mapper = new HttpExceptionMapper();

        $denied = $mapper->toResponse(new AccessDeniedHttpException('Access Denied.'));
        $unknown = $mapper->toResponse(new NotFoundHttpException('No route found.'));

        self::assertSame($unknown->getStatusCode(), $denied->getStatusCode());
        self::assertSame($unknown->getContent(), $denied->getContent());
    }

    public function testValidationErrorsCarryTheViolations(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should be between 1 and 99.', null, [], null, 'quantity', 0),
            new ConstraintViolation('This value should not be blank.', null, [], null, 'productId', ''),
        ]);
        $exception = new UnprocessableEntityHttpException(self::SECRET, new ValidationFailedException(null, $violations));

        $response = new HttpExceptionMapper()->toResponse($exception);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            [
                'type' => 'about:blank',
                'title' => 'Unprocessable Content',
                'status' => 422,
                'detail' => 'The request is invalid.',
                'errorCode' => 4002,
                'violations' => [
                    ['field' => 'quantity', 'message' => 'This value should be between 1 and 99.'],
                    ['field' => 'productId', 'message' => 'This value should not be blank.'],
                ],
            ],
            json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public function testKeepsAllowAndRetryAfterButNoOtherHeader(): void
    {
        $mapper = new HttpExceptionMapper();

        self::assertSame('GET, POST', $mapper->toResponse(new MethodNotAllowedHttpException(['GET', 'POST']))->headers->get('Allow'));
        self::assertSame('30', $mapper->toResponse(new TooManyRequestsHttpException(30))->headers->get('Retry-After'));
        self::assertNull($mapper->toResponse(new NotFoundHttpException(headers: ['X-Debug' => 'internal']))->headers->get('X-Debug'));
    }
}
