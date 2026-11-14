<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates request and response bodies against docs/openapi.yaml.
 *
 * Adapted from the reference project. It is stricter in three points: a status
 * code that the operation does not document is a violation instead of being
 * skipped, the response content type must be one the contract lists, and a
 * response documented without a body must have none. `$ref`s are resolved for
 * all components (schemas, responses), because the error responses are shared.
 */
final class OpenApiValidator
{
    /** @var array<string, mixed> */
    private readonly array $spec;
    private readonly Validator $validator;

    public function __construct(string $specFile)
    {
        $this->spec = Yaml::parseFile($specFile);
        $this->validator = new Validator();
    }

    public function hasRequestBodySchema(string $method, string $path): bool
    {
        return null !== $this->requestBodySchema($method, $path);
    }

    public function assertRequestBody(string $method, string $path, string $content): void
    {
        $where = \sprintf('Request body of %s %s', strtoupper($method), $path);
        $schema = $this->requestBodySchema($method, $path)
            ?? throw new ContractViolation($where.': the contract defines no application/json request body.');

        $this->assertMatchesSchema($schema, $content, $where);
    }

    public function assertResponse(string $method, string $path, int $statusCode, ?string $contentType, string $content): void
    {
        $where = \sprintf('Response of %s %s [%d]', strtoupper($method), $path, $statusCode);
        $responses = $this->operation($method, $path)['responses'] ?? [];
        $response = $responses[$statusCode] ?? $responses[(string) $statusCode]
            ?? throw new ContractViolation($where.': the contract does not document this status code.');
        $response = $this->resolveRefs($response);

        if (!isset($response['content'])) {
            if ('' !== $content) {
                throw new ContractViolation($where.': the contract documents no body, but the response has one.');
            }

            return;
        }

        // "application/problem+json; charset=utf-8" → "application/problem+json"
        $mediaType = strtolower(trim(explode(';', $contentType ?? '')[0]));
        $schema = $response['content'][$mediaType]['schema'] ?? throw new ContractViolation(\sprintf('%s: content type "%s" is not documented, expected one of: %s.', $where, $mediaType, implode(', ', array_keys($response['content']))));

        $this->assertMatchesSchema($schema, $content, $where);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestBodySchema(string $method, string $path): ?array
    {
        $requestBody = $this->resolveRefs($this->operation($method, $path)['requestBody'] ?? []);

        return $requestBody['content']['application/json']['schema'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(string $method, string $path): array
    {
        return $this->spec['paths'][$this->resolveSpecPath($path)][strtolower($method)]
            ?? throw new ContractViolation(\sprintf('The contract defines no operation %s %s.', strtoupper($method), $path));
    }

    /**
     * Maps a concrete path ("/carts/123/items") to its template ("/carts/{cartId}/items").
     */
    private function resolveSpecPath(string $path): string
    {
        if (isset($this->spec['paths'][$path])) {
            return $path;
        }

        foreach (array_keys($this->spec['paths']) as $specPath) {
            $parts = preg_split('~\{[^}]+\}~', $specPath) ?: [];
            $pattern = '~^'.implode('[^/]+', array_map(static fn (string $part): string => preg_quote($part, '~'), $parts)).'$~';
            if (1 === preg_match($pattern, $path)) {
                return $specPath;
            }
        }

        throw new ContractViolation(\sprintf('The contract defines no path matching %s.', $path));
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function assertMatchesSchema(array $schema, string $content, string $where): void
    {
        try {
            $data = json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ContractViolation($where.': the body is not valid JSON.', previous: $e);
        }

        $result = $this->validator->validate(
            $data,
            json_decode(json_encode($schema, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR),
        );

        if (!$result->isValid()) {
            throw new ContractViolation(\sprintf("%s does not match the OpenAPI spec:\n%s", $where, $this->formatErrors($result->error())));
        }
    }

    /**
     * Inlines every `$ref` and converts OpenAPI 3.0 `nullable` into a JSON Schema type list.
     *
     * @param array<mixed> $node
     *
     * @return array<mixed>
     */
    private function resolveRefs(array $node): array
    {
        if (isset($node['$ref'])) {
            $node = $this->resolveRef($node['$ref']);
        }

        foreach ($node as $key => $value) {
            if (\is_array($value)) {
                $node[$key] = $this->resolveRefs($value);
            }
        }

        if (true === ($node['nullable'] ?? null) && \is_string($node['type'] ?? null)) {
            $node['type'] = [$node['type'], 'null'];
            unset($node['nullable']);
        }

        return $node;
    }

    /**
     * @return array<mixed>
     */
    private function resolveRef(string $ref): array
    {
        if (!str_starts_with($ref, '#/')) {
            throw new \InvalidArgumentException(\sprintf('Only local $refs are supported, got: %s', $ref));
        }

        $node = $this->spec;
        foreach (explode('/', substr($ref, 2)) as $segment) {
            $node = $node[$segment] ?? throw new \InvalidArgumentException(\sprintf('Cannot resolve $ref: %s', $ref));
        }

        return $node;
    }

    private function formatErrors(?ValidationError $error, int $depth = 0): string
    {
        if (null === $error) {
            return '';
        }

        $message = str_repeat('  ', $depth).$this->interpolate($error->message(), $error->args());
        if ([] !== $error->data()->fullPath()) {
            $message .= ' (at /'.implode('/', $error->data()->fullPath()).')';
        }

        foreach ($error->subErrors() as $sub) {
            $message .= "\n".$this->formatErrors($sub, $depth + 1);
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function interpolate(string $message, array $args): string
    {
        foreach ($args as $key => $value) {
            $message = str_replace('{'.$key.'}', \is_scalar($value) ? (string) $value : (string) json_encode($value), $message);
        }

        return $message;
    }
}
