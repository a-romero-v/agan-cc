<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Kernel;
use Psr\Container\ContainerInterface;

final class KernelBoot
{
    private static ?ContainerInterface $container = null;

    public static function container(): ContainerInterface
    {
        if (null === self::$container) {
            require_once \dirname(__DIR__, 2).'/tests/bootstrap.php';
            $kernel = new Kernel('test', false);
            $kernel->boot();
            $raw = $kernel->getContainer();
            self::$container = $raw->has('test.service_container')
                ? $raw->get('test.service_container')
                : $raw;
        }

        return self::$container;
    }
}
