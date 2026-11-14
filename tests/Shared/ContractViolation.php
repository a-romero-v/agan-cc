<?php

declare(strict_types=1);

namespace App\Tests\Shared;

/**
 * A request or response that does not match docs/openapi.yaml.
 */
final class ContractViolation extends \RuntimeException
{
}
