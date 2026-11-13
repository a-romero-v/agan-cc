<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\TransactionInterface;
use Doctrine\DBAL\Connection;

/**
 * Transaction on the default DBAL connection.
 *
 * With SQLite every transaction starts as BEGIN IMMEDIATE (see config/packages/doctrine.yaml),
 * so the write lock is taken before the first read. Nested calls use a savepoint inside the
 * outer transaction.
 */
final readonly class DoctrineTransaction implements TransactionInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function run(callable $operation): mixed
    {
        return $this->connection->transactional(static fn (Connection $connection): mixed => $operation());
    }
}
