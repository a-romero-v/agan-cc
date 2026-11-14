<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\ApiExceptionSubscriber;
use App\Shared\Infrastructure\Http\ExceptionMapper\DomainExceptionMapper;
use App\Shared\Infrastructure\Http\ExceptionMapper\HttpExceptionMapper;
use App\Tests\Shared\Fixture\SampleConflictException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionSubscriberTest extends TestCase
{
    /** @var AbstractLogger&object{records: list<array{level: mixed, message: string, context: array<mixed>}>} */
    private AbstractLogger $logger;
    private ApiExceptionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
        $this->subscriber = new ApiExceptionSubscriber($this->logger, [new DomainExceptionMapper(), new HttpExceptionMapper()]);
    }

    public function testUsesTheFirstMapperThatSupportsTheException(): void
    {
        $response = $this->handle(new SampleConflictException(3));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(9002, $this->body($response)['errorCode']);
    }

    public function testUnknownExceptionsBecomeA500WithoutInternals(): void
    {
        $response = $this->handle(new \RuntimeException('SQLSTATE[HY000]: secret internals'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Internal error.', 'errorCode' => 4000],
            $this->body($response),
        );
    }

    public function testLogsServerErrorsAsErrorWithTheException(): void
    {
        $exception = new \RuntimeException('boom');

        $this->handle($exception);

        self::assertCount(1, $this->logger->records);
        self::assertSame('error', $this->logger->records[0]['level']);
        self::assertSame($exception, $this->logger->records[0]['context']['exception']);
    }

    public function testLogsClientErrorsAsInfo(): void
    {
        $this->handle(new NotFoundHttpException('No route found.'));

        self::assertCount(1, $this->logger->records);
        self::assertSame('info', $this->logger->records[0]['level']);
        self::assertSame(['exception_class' => NotFoundHttpException::class], $this->logger->records[0]['context']);
    }

    private function handle(\Throwable $exception): Response
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
