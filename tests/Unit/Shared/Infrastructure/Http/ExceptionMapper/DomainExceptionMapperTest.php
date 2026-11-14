<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\ExceptionMapper;

use App\Shared\Domain\Exception\AbstractDomainException;
use App\Shared\Infrastructure\Http\ExceptionMapper\DomainExceptionMapper;
use App\Tests\Shared\Fixture\SampleConflictException;
use App\Tests\Shared\Fixture\SampleInvalidException;
use App\Tests\Shared\Fixture\SampleNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainExceptionMapperTest extends TestCase
{
    public function testSupportsOnlyDomainExceptions(): void
    {
        $mapper = new DomainExceptionMapper();

        self::assertTrue($mapper->supports(new SampleInvalidException()));
        self::assertFalse($mapper->supports(new \DomainException('built-in, not ours')));
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('exceptions')]
    public function testMapsTheErrorTypeToAStatus(AbstractDomainException $exception, array $expected): void
    {
        $response = new DomainExceptionMapper()->toResponse($exception);

        self::assertSame($expected['status'], $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame($expected, json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{AbstractDomainException, array<string, mixed>}>
     */
    public static function exceptions(): iterable
    {
        yield 'not found' => [new SampleNotFoundException('abc'), [
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => 'Sample "abc" not found in {{ place }}.',
            'errorCode' => 9001,
        ]];
        yield 'conflict' => [new SampleConflictException(3), [
            'type' => 'about:blank',
            'title' => 'Conflict',
            'status' => 409,
            'detail' => 'Only 3 left.',
            'errorCode' => 9002,
        ]];
        yield 'invalid' => [new SampleInvalidException(), [
            'type' => 'about:blank',
            'title' => 'Unprocessable Content',
            'status' => 422,
            'detail' => 'Sample is invalid.',
            'errorCode' => 9003,
        ]];
    }
}
