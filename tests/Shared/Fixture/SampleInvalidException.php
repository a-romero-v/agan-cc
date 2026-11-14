<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fixture;

use App\Shared\Domain\Exception\AbstractDomainException;
use App\Shared\Domain\Exception\DomainError;
use App\Shared\Domain\Exception\ErrorType;

#[DomainError(errorCode: 9003, type: ErrorType::Invalid, message: 'Sample is invalid.')]
final class SampleInvalidException extends AbstractDomainException
{
}
