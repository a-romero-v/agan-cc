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
 */
final readonly class SymfonyCommandBus implements CommandBusInterface
{
    private HandlerLocator $handlers;

    public function __construct(ContainerInterface $locator)
    {
        $this->handlers = new HandlerLocator($locator);
    }

    public function dispatch(object $command): void
    {
        $this->handlers->handlerFor($command)($command);
    }
}
