<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\AbstractDomainException;
use App\Shared\Domain\Exception\ErrorType;
use App\Tests\Shared\Fixture\SampleConflictException;
use App\Tests\Shared\Fixture\SampleInvalidException;
use App\Tests\Shared\Fixture\SampleNotFoundException;
use PHPUnit\Framework\TestCase;

final class AbstractDomainExceptionTest extends TestCase
{
    public function testReadsCodeAndTypeFromTheAttribute(): void
    {
        $exception = new SampleInvalidException();

        self::assertSame(9003, $exception->errorCode());
        self::assertSame(9003, $exception->getCode());
        self::assertSame(ErrorType::Invalid, $exception->type());
        self::assertSame('Sample is invalid.', $exception->getMessage());
    }

    public function testFillsPlaceholdersFromTheContext(): void
    {
        self::assertSame('Only 3 left.', new SampleConflictException(3)->getMessage());
    }

    public function testLeavesUnknownPlaceholdersUntouched(): void
    {
        self::assertSame('Sample "abc" not found in {{ place }}.', new SampleNotFoundException('abc')->getMessage());
    }

    public function testKeepsThePreviousException(): void
    {
        $previous = new \RuntimeException('cause');

        self::assertSame($previous, new SampleNotFoundException('abc', $previous)->getPrevious());
    }

    public function testFailsWithoutTheAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing #[DomainError] attribute');

        new class extends AbstractDomainException {};
    }
}
