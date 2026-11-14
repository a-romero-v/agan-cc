<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testListensToEveryResponse(): void
    {
        self::assertSame([KernelEvents::RESPONSE => 'onKernelResponse'], SecurityHeadersSubscriber::getSubscribedEvents());
    }

    public function testAddsNosniff(): void
    {
        $response = new Response('{}', 500);
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        new SecurityHeadersSubscriber()->onKernelResponse($event);

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }
}
