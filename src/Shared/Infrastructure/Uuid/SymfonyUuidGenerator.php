<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Uuid;

use App\Shared\Domain\Port\UuidGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * UUID version 7: random, but ordered by creation time, which keeps
 * primary-key indexes compact.
 */
final readonly class SymfonyUuidGenerator implements UuidGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
