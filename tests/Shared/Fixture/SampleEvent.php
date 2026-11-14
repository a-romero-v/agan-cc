<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fixture;

use App\Shared\Domain\DomainEvent;

final readonly class SampleEvent implements DomainEvent
{
    public function __construct(public string $id)
    {
    }
}
