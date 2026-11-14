<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Uuid;

use App\Shared\Infrastructure\Uuid\SymfonyUuidGenerator;
use PHPUnit\Framework\TestCase;

final class SymfonyUuidGeneratorTest extends TestCase
{
    public function testGeneratesACanonicalUuidVersion7(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            new SymfonyUuidGenerator()->generate(),
        );
    }

    public function testGeneratesUniqueIdsOrderedByCreation(): void
    {
        $generator = new SymfonyUuidGenerator();

        $ids = array_map(static fn (): string => $generator->generate(), range(1, 100));

        $sorted = $ids;
        sort($sorted);
        self::assertCount(100, array_unique($ids));
        self::assertSame($sorted, $ids);
    }
}
