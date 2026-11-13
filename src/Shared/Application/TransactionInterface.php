<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Runs an operation atomically. Command handlers own the transaction.
 *
 * A call made while a transaction is already running joins that transaction.
 * If the operation throws, everything is rolled back and the exception is rethrown.
 */
interface TransactionInterface
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(callable $operation): mixed;
}
