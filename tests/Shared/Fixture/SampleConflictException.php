<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fixture;

use App\Shared\Domain\Exception\AbstractDomainException;
use App\Shared\Domain\Exception\DomainError;
use App\Shared\Domain\Exception\ErrorType;

#[DomainError(errorCode: 9002, type: ErrorType::Conflict, message: 'Only {{ available }} left.')]
final class SampleConflictException extends AbstractDomainException
{
    public function __construct(int $available)
    {
        parent::__construct(['available' => $available]);
    }
}
