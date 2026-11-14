<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fixture;

use App\Shared\Domain\Exception\AbstractDomainException;
use App\Shared\Domain\Exception\DomainError;
use App\Shared\Domain\Exception\ErrorType;

#[DomainError(errorCode: 9001, type: ErrorType::NotFound, message: 'Sample "{{ id }}" not found in {{ place }}.')]
final class SampleNotFoundException extends AbstractDomainException
{
    public function __construct(string $id, ?\Throwable $previous = null)
    {
        parent::__construct(['id' => $id], $previous);
    }
}
