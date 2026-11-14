<?php

declare(strict_types=1);

namespace App\Tests\Unit\Contract;

use App\Tests\Shared\TemporaryDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * bin/validate-openapi is a QA gate: if it accepted everything, `make qa`
 * would stay green on a broken contract. The script runs as a process, the
 * way `composer app:validate:openapi` runs it.
 */
final class ValidateOpenApiScriptTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory('openapi-validate');
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testAcceptsAValidSpec(): void
    {
        [$exitCode, $output] = $this->validate(Yaml::dump(self::validSpec(), 10, 2));

        self::assertSame(0, $exitCode, $output);
    }

    /**
     * @param array<string, mixed> $spec
     */
    #[DataProvider('brokenSpecs')]
    public function testRejectsABrokenSpec(array $spec, string $expectedMessage): void
    {
        [$exitCode, $output] = $this->validate(Yaml::dump($spec, 10, 2));

        self::assertSame(1, $exitCode);
        self::assertStringContainsString($expectedMessage, $output);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function brokenSpecs(): iterable
    {
        $spec = self::validSpec();
        unset($spec['openapi']);
        yield 'missing openapi version' => [$spec, 'Missing required field: openapi'];

        $spec = self::validSpec();
        unset($spec['info']['title']);
        yield 'missing info.title' => [$spec, 'Missing required field: info.title'];

        $spec = self::validSpec();
        unset($spec['paths']);
        yield 'missing paths' => [$spec, 'Missing required field: paths'];

        $spec = self::validSpec();
        unset($spec['paths']['/things']['get']['responses']);
        yield 'operation without responses' => [$spec, 'Operation "get /things" is missing "responses"'];

        $spec = self::validSpec();
        $spec['paths']['/things']['post'] = $spec['paths']['/things']['get'];
        yield 'duplicate operationId' => [$spec, 'Duplicate operationId "listThings"'];

        $spec = self::validSpec();
        $spec['paths']['/things']['get']['responses']['200']['content']['application/json']['schema']['$ref'] = '#/components/schemas/Missing';
        yield 'unresolvable $ref' => [$spec, 'Unresolvable $ref: #/components/schemas/Missing'];

        $spec = self::validSpec();
        $spec['paths']['/things']['get']['security'] = [['unknown' => []]];
        yield 'undefined security scheme' => [$spec, 'Undefined security scheme "unknown"'];
    }

    public function testRejectsInvalidYaml(): void
    {
        [$exitCode, $output] = $this->validate("openapi: [3.1.0\n");

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('YAML syntax error', $output);
    }

    public function testRejectsAMissingFile(): void
    {
        [$exitCode, $output] = $this->runScript($this->directory->path.'/no-such-file.yaml');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('File not found', $output);
    }

    /**
     * @return array<string, mixed>
     */
    private static function validSpec(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Sample', 'version' => '1.0.0'],
            'paths' => [
                '/things' => [
                    'get' => [
                        'operationId' => 'listThings',
                        'security' => [['bearer' => []]],
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Thing']]],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']],
                'schemas' => ['Thing' => ['type' => 'object']],
            ],
        ];
    }

    /**
     * @return array{int, string}
     */
    private function validate(string $yaml): array
    {
        return $this->runScript($this->directory->write('openapi.yaml', $yaml));
    }

    /**
     * @return array{int, string}
     */
    private function runScript(string $file): array
    {
        $script = \dirname(__DIR__, 3).'/bin/validate-openapi';

        exec(\sprintf('%s %s %s 2>&1', escapeshellarg(\PHP_BINARY), escapeshellarg($script), escapeshellarg($file)), $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
