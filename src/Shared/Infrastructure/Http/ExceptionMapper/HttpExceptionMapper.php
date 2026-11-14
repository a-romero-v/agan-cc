<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\ExceptionMapper;

use App\Shared\Infrastructure\Http\ExceptionMapperInterface;
use App\Shared\Infrastructure\Http\ProblemDetailsResponse;
use App\Shared\Infrastructure\Http\SharedErrorCode;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Maps every HTTP exception of the framework: router 404 and 405, malformed
 * JSON (400), wrong content type (415), rate limit (429), access denied and
 * validation errors.
 *
 * The `detail` is a fixed text per case, never the exception message: the
 * framework's messages name routes, classes and parameters.
 *
 * Access denied becomes the same 404 as an unknown resource, so a foreign
 * cart cannot be told apart from a missing one (R6).
 */
final readonly class HttpExceptionMapper implements ExceptionMapperInterface
{
    /** Headers of the exception that are part of the API contract. */
    private const array PASSED_HEADERS = ['Allow', 'Retry-After'];

    public function supports(\Throwable $exception): bool
    {
        return $exception instanceof HttpExceptionInterface;
    }

    public function toResponse(\Throwable $exception): ProblemDetailsResponse
    {
        \assert($exception instanceof HttpExceptionInterface);

        $validation = $exception->getPrevious();
        if ($validation instanceof ValidationFailedException) {
            return new ProblemDetailsResponse(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                SharedErrorCode::ValidationFailed->value,
                'The request is invalid.',
                ['violations' => $this->violations($validation)],
            );
        }

        if ($exception instanceof AccessDeniedHttpException) {
            return $this->notFound();
        }

        $status = $exception->getStatusCode();

        [$code, $detail] = match (true) {
            Response::HTTP_NOT_FOUND === $status => [SharedErrorCode::NotFound, 'Resource not found.'],
            Response::HTTP_UNPROCESSABLE_ENTITY === $status => [SharedErrorCode::ValidationFailed, 'The request is invalid.'],
            Response::HTTP_UNAUTHORIZED === $status => [SharedErrorCode::Unauthorized, 'Missing or invalid credentials.'],
            Response::HTTP_METHOD_NOT_ALLOWED === $status => [SharedErrorCode::MethodNotAllowed, 'Method not allowed.'],
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE === $status => [SharedErrorCode::UnsupportedMediaType, 'The request body must be application/json.'],
            Response::HTTP_TOO_MANY_REQUESTS === $status => [SharedErrorCode::RateLimitExceeded, 'Rate limit exceeded.'],
            $status >= 500 => [SharedErrorCode::InternalError, 'Internal error.'],
            // 400 and every client error without a code of its own.
            default => [SharedErrorCode::MalformedRequest, 'Malformed request.'],
        };

        return new ProblemDetailsResponse($status, $code->value, $detail, headers: $this->passedHeaders($exception));
    }

    private function notFound(): ProblemDetailsResponse
    {
        return new ProblemDetailsResponse(Response::HTTP_NOT_FOUND, SharedErrorCode::NotFound->value, 'Resource not found.');
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function violations(ValidationFailedException $exception): array
    {
        $violations = [];
        foreach ($exception->getViolations() as $violation) {
            $violations[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $violations;
    }

    /**
     * @return array<string, string>
     */
    private function passedHeaders(HttpExceptionInterface $exception): array
    {
        $headers = [];
        foreach ($exception->getHeaders() as $name => $value) {
            foreach (self::PASSED_HEADERS as $passed) {
                if (0 === strcasecmp($passed, (string) $name)) {
                    $headers[$passed] = (string) $value;
                }
            }
        }

        return $headers;
    }
}
