<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Strategy that turns one family of exceptions into a Problem Details response.
 *
 * Implementations are tagged automatically and tried in turn by
 * ApiExceptionSubscriber; the first one that supports the exception wins.
 */
#[AutoconfigureTag('app.exception_mapper')]
interface ExceptionMapperInterface
{
    public function supports(\Throwable $exception): bool;

    public function toResponse(\Throwable $exception): ProblemDetailsResponse;
}
