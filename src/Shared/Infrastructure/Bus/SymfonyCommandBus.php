<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\CommandBusInterface;
use Psr\Container\ContainerInterface;

/**
 * In-process command bus: looks up the handler in a service locator
 * (command class => handler, see config/services.yaml) and calls handle().
 *
 * No Messenger involved: commands run synchronously inside the request.
 *
 * A missing or malformed handler is a wiring mistake, not a runtime
 * condition, hence the LogicException (which ends as a 500).
 */
final readonly class SymfonyCommandBus implements CommandBusInterface
{
    public function __construct(private ContainerInterface $locator)
    {
    }

    public function dispatch(object $command): void
    {
        $class = $command::class;

        if (!$this->locator->has($class)) {
            throw new \LogicException(\sprintf('No handler registered for "%s".', $class));
        }

        $handler = $this->locator->get($class);

        if (!\is_object($handler) || !method_exists($handler, 'handle')) {
            throw new \LogicException(\sprintf('The handler registered for "%s" has no handle() method.', $class));
        }

        $handler->handle($command);
    }
}
