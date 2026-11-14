<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fixture;

/**
 * Pure PHP, like the Application event handlers: no attribute, no interface.
 */
final class SampleEventHandler
{
    /** @var list<SampleEvent> */
    public array $handled = [];

    public function __invoke(SampleEvent $event): void
    {
        $this->handled[] = $event;
    }
}
