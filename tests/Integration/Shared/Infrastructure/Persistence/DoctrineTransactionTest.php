<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\DoctrineTransaction;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineTransactionTest extends KernelTestCase
{
    private const string TABLE = 'transaction_test';

    private Connection $connection;
    private DoctrineTransaction $transaction;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement(\sprintf('CREATE TABLE IF NOT EXISTS %s (value TEXT NOT NULL)', self::TABLE));
        $this->connection->executeStatement(\sprintf('DELETE FROM %s', self::TABLE));

        $this->transaction = new DoctrineTransaction($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS %s', self::TABLE));

        parent::tearDown();
    }

    #[Test]
    public function itCommitsAndReturnsTheResultOfTheOperation(): void
    {
        $result = $this->transaction->run(function (): string {
            $this->insert('committed');

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertSame(['committed'], $this->storedValues());
    }

    #[Test]
    public function itRollsBackAndRethrowsWhenTheOperationFails(): void
    {
        try {
            $this->transaction->run(function (): never {
                $this->insert('lost');

                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
            self::assertSame([], $this->storedValues());

            return;
        }

        self::fail('The exception of the operation was not rethrown.');
    }

    #[Test]
    public function aNestedCallJoinsTheOuterTransaction(): void
    {
        try {
            $this->transaction->run(function (): never {
                $this->transaction->run(fn () => $this->insert('inner'));

                throw new \RuntimeException('outer failed');
            });
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->storedValues(), 'The inner call must not commit on its own.');
    }

    /**
     * R14: the write lock is taken when the transaction starts, before anything is read.
     * A deferred transaction would take no lock here and the second connection would succeed.
     */
    #[Test]
    public function itTakesTheWriteLockWhenTheTransactionStarts(): void
    {
        $competitor = $this->competingConnection();

        $lockedOut = $this->transaction->run(static function () use ($competitor): bool {
            try {
                $competitor->executeStatement('BEGIN IMMEDIATE');
            } catch (LockWaitTimeoutException) {
                return true;
            }

            $competitor->executeStatement('ROLLBACK');

            return false;
        });

        self::assertTrue($lockedOut, 'A second writer must be locked out while the transaction is open.');

        // The lock is released on commit.
        $competitor->executeStatement('BEGIN IMMEDIATE');
        $competitor->executeStatement('ROLLBACK');
        $competitor->close();
    }

    #[Test]
    public function itWaitsForTheWriteLockInsteadOfFailingImmediately(): void
    {
        self::assertSame(5000, (int) $this->connection->fetchOne('PRAGMA busy_timeout'));
    }

    /**
     * A second connection to the same database file that gives up at once when the file is locked.
     */
    private function competingConnection(): Connection
    {
        $params = $this->connection->getParams();
        $params['driverOptions'] = [\PDO::ATTR_TIMEOUT => 0];

        return DriverManager::getConnection($params);
    }

    private function insert(string $value): void
    {
        $this->connection->insert(self::TABLE, ['value' => $value]);
    }

    /**
     * @return list<mixed>
     */
    private function storedValues(): array
    {
        return $this->connection->fetchFirstColumn(\sprintf('SELECT value FROM %s', self::TABLE));
    }
}
