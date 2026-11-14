<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns every unhandled exception into a Problem Details response.
 *
 * The first mapper that supports the exception wins. Anything no mapper
 * knows becomes a 500 without internals: the cause goes to the log, never
 * to the client.
 *
 * Logging: status >= 500 as error (with the exception), everything else as info.
 */
final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<ExceptionMapperInterface> $mappers
     */
    public function __construct(
        private LoggerInterface $logger,
        #[AutowireIterator('app.exception_mapper')]
        private iterable $mappers,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $response = $this->map($exception);

        if ($response->getStatusCode() >= 500) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        } else {
            $this->logger->info($exception->getMessage(), ['exception_class' => $exception::class]);
        }

        $event->setResponse($response);
    }

    private function map(\Throwable $exception): ProblemDetailsResponse
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->supports($exception)) {
                return $mapper->toResponse($exception);
            }
        }

        return new ProblemDetailsResponse(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            SharedErrorCode::InternalError->value,
            'Internal error.',
        );
    }
}
