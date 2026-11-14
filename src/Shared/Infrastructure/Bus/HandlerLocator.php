<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use Psr\Container\ContainerInterface;

/**
 * Finds the handler of a message by the message's class name.
 *
 * Wraps the service locator configured in config/services.yaml. A missing or
 * malformed handler is a wiring mistake, not a runtime condition, hence the
 * LogicException (which ends as a 500).
 */
final readonly class HandlerLocator
{
    public function __construct(private ContainerInterface $locator)
    {
    }

    /**
     * @return callable(object): mixed
     */
    public function handlerFor(object $message): callable
    {
        $class = $message::class;

        if (!$this->locator->has($class)) {
            throw new \LogicException(\sprintf('No handler registered for "%s".', $class));
        }

        $handler = $this->locator->get($class);

        if (!\is_object($handler) || !method_exists($handler, 'handle')) {
            throw new \LogicException(\sprintf('The handler registered for "%s" has no handle() method.', $class));
        }

        return $handler->handle(...);
    }
}
