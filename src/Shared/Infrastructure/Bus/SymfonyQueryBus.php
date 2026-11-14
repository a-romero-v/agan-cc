<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\QueryBusInterface;
use Psr\Container\ContainerInterface;

/**
 * In-process query bus: looks up the handler in a service locator
 * (query class => handler, see config/services.yaml) and returns its result.
 */
final readonly class SymfonyQueryBus implements QueryBusInterface
{
    private HandlerLocator $handlers;

    public function __construct(ContainerInterface $locator)
    {
        $this->handlers = new HandlerLocator($locator);
    }

    public function ask(object $query): mixed
    {
        return $this->handlers->handlerFor($query)($query);
    }
}
