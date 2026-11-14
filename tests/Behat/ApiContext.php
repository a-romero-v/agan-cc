<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Tests\Shared\OpenApiValidator;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * HTTP steps: full round-trips through the kernel, snapshot assertions on the
 * JSON response and validation against the API contract.
 *
 * Adapted from the reference project. Authentication and data setup steps are
 * added together with the features that need them.
 */
final class ApiContext implements Context
{
    private const string ANY = '@any';

    private readonly KernelBrowser $client;
    private readonly OpenApiValidator $openApiValidator;

    public function __construct()
    {
        $client = KernelBoot::container()->get('test.client');
        \assert($client instanceof KernelBrowser);
        $this->client = $client;
        $this->openApiValidator = new OpenApiValidator(\dirname(__DIR__, 2).'/docs/openapi.yaml');
    }

    #[BeforeScenario]
    public function reset(): void
    {
        $this->client->restart();
    }

    #[When('I send a :method request to :url')]
    public function iSendARequest(string $method, string $url): void
    {
        $this->client->request($method, $url);
    }

    #[When('I send a :method request to :url with body:')]
    public function iSendARequestWithBody(string $method, string $url, PyStringNode $body): void
    {
        $this->client->request($method, $url, server: ['CONTENT_TYPE' => 'application/json'], content: $body->getRaw());
    }

    #[Then('the response status code should be :code')]
    public function theResponseStatusCodeShouldBe(int $code): void
    {
        $response = $this->client->getResponse();
        if ($response->getStatusCode() !== $code) {
            throw new \RuntimeException(\sprintf("Expected status %d but got %d.\nResponse body: %s", $code, $response->getStatusCode(), (string) $response->getContent()));
        }
    }

    #[Then('the response header :name should be :value')]
    public function theResponseHeaderShouldBe(string $name, string $value): void
    {
        $actual = $this->client->getResponse()->headers->get($name);
        if ($actual !== $value) {
            throw new \RuntimeException(\sprintf('Expected header %s to be %s but got %s.', $name, json_encode($value), json_encode($actual)));
        }
    }

    #[Then('the request body matches the OpenAPI spec')]
    public function theRequestBodyMatchesTheOpenApiSpec(): void
    {
        $request = $this->client->getRequest();

        $this->openApiValidator->assertRequestBody($request->getMethod(), $request->getPathInfo(), $request->getContent());
    }

    #[Then('the response matches the OpenAPI spec')]
    public function theResponseMatchesTheOpenApiSpec(): void
    {
        $request = $this->client->getRequest();
        $response = $this->client->getResponse();

        $this->openApiValidator->assertResponse(
            $request->getMethod(),
            $request->getPathInfo(),
            $response->getStatusCode(),
            $response->headers->get('Content-Type'),
            (string) $response->getContent(),
        );
    }

    /**
     * Compares the whole response. "@any" stands for a generated value (ID, token, timestamp).
     */
    #[Then('the JSON response is:')]
    public function theJsonResponseIs(PyStringNode $expected): void
    {
        $this->assertJsonMatches(
            json_decode($expected->getRaw(), true, 512, \JSON_THROW_ON_ERROR),
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR),
            'response',
        );
    }

    private function assertJsonMatches(mixed $expected, mixed $actual, string $path): void
    {
        if (self::ANY === $expected) {
            return;
        }

        if (!\is_array($expected)) {
            if ($actual !== $expected) {
                throw new \RuntimeException(\sprintf('At %s: expected %s but got %s', $path, json_encode($expected), json_encode($actual)));
            }

            return;
        }

        if (!\is_array($actual)) {
            throw new \RuntimeException(\sprintf('At %s: expected array but got %s', $path, get_debug_type($actual)));
        }

        if ([] !== $missing = array_diff(array_keys($expected), array_keys($actual))) {
            throw new \RuntimeException(\sprintf('At %s: missing keys: %s', $path, implode(', ', $missing)));
        }
        if ([] !== $extra = array_diff(array_keys($actual), array_keys($expected))) {
            throw new \RuntimeException(\sprintf('At %s: unexpected keys: %s', $path, implode(', ', $extra)));
        }

        foreach ($expected as $key => $value) {
            $this->assertJsonMatches($value, $actual[$key], $path.'.'.$key);
        }
    }
}
