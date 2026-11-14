<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Console\OpenApiBuilder;
use App\Tests\Shared\TemporaryDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The sync check compares the builder with its own earlier output, so it
 * cannot notice a builder that merges wrongly. This test does.
 */
final class OpenApiBuilderTest extends TestCase
{
    private TemporaryDirectory $project;

    protected function setUp(): void
    {
        $this->project = new TemporaryDirectory('openapi-build');

        $this->project->write('api-contract/base.yaml', <<<'YAML'
            openapi: 3.1.0
            info:
              title: Sample
              version: 1.0.0
            components:
              securitySchemes:
                bearer:
                  type: http
                  scheme: bearer
            YAML);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testKeepsTheBaseDocument(): void
    {
        $spec = $this->build();

        self::assertSame('3.1.0', $spec['openapi']);
        self::assertSame(['title' => 'Sample', 'version' => '1.0.0'], $spec['info']);
        self::assertSame(['bearer' => ['type' => 'http', 'scheme' => 'bearer']], $spec['components']['securitySchemes']);
    }

    public function testCollectsOneSchemaPerFile(): void
    {
        $this->project->write('api-contract/schemas/Thing.yaml', "Thing:\n  type: object\n");
        $this->project->write('api-contract/schemas/Other.yaml', "Other:\n  type: string\n");

        self::assertEquals(
            ['Thing' => ['type' => 'object'], 'Other' => ['type' => 'string']],
            $this->build()['components']['schemas'],
        );
    }

    public function testMergesTheOperationsOfOnePathFromSeveralFiles(): void
    {
        $this->project->write('api-contract/paths/things/list.yaml', "/things:\n  get:\n    operationId: listThings\n");
        $this->project->write('api-contract/paths/things/create.yaml', "/things:\n  post:\n    operationId: createThing\n");
        $this->project->write('api-contract/paths/others/get.yaml', "/others/{id}:\n  get:\n    operationId: getOther\n");

        self::assertEquals(
            [
                '/things' => [
                    'get' => ['operationId' => 'listThings'],
                    'post' => ['operationId' => 'createThing'],
                ],
                '/others/{id}' => ['get' => ['operationId' => 'getOther']],
            ],
            $this->build()['paths'],
        );
    }

    public function testWithoutFragmentsPathsAndSchemasAreEmpty(): void
    {
        $spec = $this->build();

        self::assertSame([], $spec['paths']);
        self::assertSame([], $spec['components']['schemas']);
    }

    public function testTheOutputIsStable(): void
    {
        $this->project->write('api-contract/schemas/Thing.yaml', "Thing:\n  type: object\n");
        $builder = new OpenApiBuilder($this->project->path);

        self::assertSame($builder->build(), $builder->build());
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $spec = Yaml::parse(new OpenApiBuilder($this->project->path)->build());
        self::assertIsArray($spec);

        return $spec;
    }
}
