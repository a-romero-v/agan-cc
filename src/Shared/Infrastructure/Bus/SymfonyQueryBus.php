<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\QueryBusInterface;
use Psr\Container\ContainerInterface;

/**
 * In-process query bus: looks up the handler in a service locator
 * (query class => handler, see config/services.yaml) and returns its result.
 *
 * A missing or malformed handler is a wiring mistake, not a runtime
 * condition, hence the LogicException (which ends as a 500).
 */
final readonly class SymfonyQueryBus implements QueryBusInterface
{
    public function __construct(private ContainerInterface $locator)
    {
    }

    public function ask(object $query): mixed
    {
        $class = $query::class;

        if (!$this->locator->has($class)) {
            throw new \LogicException(\sprintf('No handler registered for "%s".', $class));
        }

        $handler = $this->locator->get($class);

        if (!\is_object($handler) || !method_exists($handler, 'handle')) {
            throw new \LogicException(\sprintf('The handler registered for "%s" has no handle() method.', $class));
        }

        return $handler->handle($query);
    }
}
