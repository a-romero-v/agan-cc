<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\ExceptionMapper;

use App\Shared\Domain\Exception\AbstractDomainException;
use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Infrastructure\Http\ExceptionMapperInterface;
use App\Shared\Infrastructure\Http\ProblemDetailsResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maps every domain exception. The only place that turns an ErrorType into
 * an HTTP status, so a new exception needs no change here; only a new
 * ErrorType does.
 */
final readonly class DomainExceptionMapper implements ExceptionMapperInterface
{
    public function supports(\Throwable $exception): bool
    {
        return $exception instanceof AbstractDomainException;
    }

    public function toResponse(\Throwable $exception): ProblemDetailsResponse
    {
        \assert($exception instanceof AbstractDomainException);

        $status = match ($exception->type()) {
            ErrorType::NotFound => Response::HTTP_NOT_FOUND,
            ErrorType::Conflict => Response::HTTP_CONFLICT,
            ErrorType::Invalid => Response::HTTP_UNPROCESSABLE_ENTITY,
        };

        return new ProblemDetailsResponse($status, $exception->errorCode(), $exception->getMessage());
    }
}
