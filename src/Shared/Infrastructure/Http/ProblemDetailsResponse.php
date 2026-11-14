<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 9457 Problem Details with the extension member `errorCode`.
 *
 * The single place that knows the shape of an error body. `type` is always
 * "about:blank", so `title` is the standard phrase of the status code.
 */
final class ProblemDetailsResponse extends JsonResponse
{
    /**
     * @param array<string, mixed>  $extensions further extension members, e.g. `violations`
     * @param array<string, string> $headers
     */
    public function __construct(int $status, int $errorCode, string $detail, array $extensions = [], array $headers = [])
    {
        parent::__construct(
            [
                'type' => 'about:blank',
                'title' => Response::$statusTexts[$status] ?? 'Error',
                'status' => $status,
                'detail' => $detail,
                'errorCode' => $errorCode,
            ] + $extensions,
            $status,
            ['Content-Type' => 'application/problem+json'] + $headers,
        );
    }
}
