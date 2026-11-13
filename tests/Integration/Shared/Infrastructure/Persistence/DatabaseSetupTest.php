<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the database assumptions the rest of the project relies on.
 */
final class DatabaseSetupTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    #[Test]
    public function itUsesTheSqliteFileOfTheTestEnvironment(): void
    {
        self::assertInstanceOf(SQLitePlatform::class, $this->connection->getDatabasePlatform());

        $path = $this->connection->getParams()['path'] ?? null;

        self::assertIsString($path);
        self::assertStringEndsWith('/var/data_test.db', $path);
    }

    #[Test]
    public function itEnforcesForeignKeys(): void
    {
        self::assertSame(1, (int) $this->connection->fetchOne('PRAGMA foreign_keys'));
    }
}
