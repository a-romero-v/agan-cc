<?php

declare(strict_types=1);

namespace App\Product\Domain\Exception;

use App\Shared\Domain\Exception\AbstractDomainException;
use App\Shared\Domain\Exception\DomainError;
use App\Shared\Domain\Exception\ErrorType;

#[DomainError(errorCode: 2001, type: ErrorType::NotFound, message: 'Product "{{ id }}" not found.')]
final class ProductNotFoundException extends AbstractDomainException
{
    public function __construct(string $id)
    {
        parent::__construct(['id' => $id]);
    }
}
