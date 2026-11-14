<?php

declare(strict_types=1);

namespace App\Shared\Domain\Port;

/**
 * The only source of new IDs. IDs are generated before persisting, so an
 * aggregate has its identity from the start.
 */
interface UuidGeneratorInterface
{
    /**
     * @return string a new UUID in its canonical form (lower case, with dashes)
     */
    public function generate(): string;
}
