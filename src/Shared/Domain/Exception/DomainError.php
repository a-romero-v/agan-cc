<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Metadata of a domain exception: stable numeric code, business-level type
 * and message template. `{{ name }}` tokens in the message are replaced from
 * the context passed to AbstractDomainException.
 *
 * Code ranges: 1xxx Cart, 2xxx Product, 3xxx User, 4xxx Shared, 5xxx Inventory.
 *
 *   #[DomainError(errorCode: 2001, type: ErrorType::NotFound, message: 'Product "{{ id }}" not found.')]
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class DomainError
{
    public function __construct(
        public int $errorCode,
        public ErrorType $type,
        public string $message,
    ) {
    }
}
