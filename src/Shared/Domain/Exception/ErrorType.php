<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Business-level kind of a domain error. The domain knows no HTTP:
 * Infrastructure decides which status code each kind becomes.
 */
enum ErrorType
{
    /** The thing asked for does not exist. */
    case NotFound;

    /** The request is valid but clashes with the current state. */
    case Conflict;

    /** The request violates a business rule. */
    case Invalid;
}
