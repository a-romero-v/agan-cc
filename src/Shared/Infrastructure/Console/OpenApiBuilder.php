<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Merges the fragments in api-contract/ into one OpenAPI document.
 *
 * base.yaml provides everything except the schemas and the paths, which are
 * collected from one file per schema and one file per operation.
 */
final readonly class OpenApiBuilder
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function build(): string
    {
        $contractDir = $this->projectDir.'/api-contract';

        $spec = Yaml::parseFile($contractDir.'/base.yaml');
        $spec['paths'] = [];
        $spec['components']['schemas'] = [];

        foreach (glob($contractDir.'/schemas/*.yaml') ?: [] as $file) {
            $spec['components']['schemas'] = array_merge(
                $spec['components']['schemas'],
                Yaml::parseFile($file),
            );
        }

        foreach (glob($contractDir.'/paths/**/*.yaml') ?: [] as $file) {
            $paths = Yaml::parseFile($file);
            foreach ($paths as $path => $operations) {
                $spec['paths'][$path] = array_merge($spec['paths'][$path] ?? [], $operations);
            }
        }

        $yaml = Yaml::dump($spec, 10, 2);

        return preg_replace('/:\s*\{\s*\}/', ': []', $yaml) ?? $yaml;
    }
}
