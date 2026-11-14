<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

/**
 * Generic error codes (4xxx) that are not tied to a bounded context.
 * See .claude/rules/errors.md for the table.
 */
enum SharedErrorCode: int
{
    case InternalError = 4000;
    case NotFound = 4001;
    case ValidationFailed = 4002;
    case MalformedRequest = 4003;
    case Unauthorized = 4004;
    case MethodNotAllowed = 4005;
    case UnsupportedMediaType = 4006;
    case RateLimitExceeded = 4007;
}
