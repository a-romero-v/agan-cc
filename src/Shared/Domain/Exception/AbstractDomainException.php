<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Base class of all domain exceptions.
 *
 * Reads the #[DomainError] attribute of the concrete class and fills the
 * message placeholders from the context. Subclasses only add a typed
 * constructor:
 *
 *   #[DomainError(errorCode: 2001, type: ErrorType::NotFound, message: 'Product "{{ id }}" not found.')]
 *   final class ProductNotFoundException extends AbstractDomainException
 *   {
 *       public function __construct(string $id)
 *       {
 *           parent::__construct(['id' => $id]);
 *       }
 *   }
 *
 * The name avoids a clash with PHP's built-in \DomainException.
 */
abstract class AbstractDomainException extends \Exception
{
    private readonly int $errorCode;
    private readonly ErrorType $type;

    /**
     * @param array<string, string|int> $context values for the `{{ name }}` placeholders of the message
     */
    public function __construct(array $context = [], ?\Throwable $previous = null)
    {
        $attributes = new \ReflectionClass($this)->getAttributes(DomainError::class);

        if ([] === $attributes) {
            throw new \LogicException(\sprintf('Missing #[DomainError] attribute on %s.', static::class));
        }

        $error = $attributes[0]->newInstance();
        $this->errorCode = $error->errorCode;
        $this->type = $error->type;

        $message = preg_replace_callback(
            '/{{\s*(\w+)\s*}}/',
            static fn (array $matches): string => (string) ($context[$matches[1]] ?? $matches[0]),
            $error->message,
        ) ?? $error->message;

        parent::__construct($message, $error->errorCode, $previous);
    }

    public function errorCode(): int
    {
        return $this->errorCode;
    }

    public function type(): ErrorType
    {
        return $this->type;
    }
}
