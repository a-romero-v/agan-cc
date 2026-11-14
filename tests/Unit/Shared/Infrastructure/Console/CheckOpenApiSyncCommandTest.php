<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Console\CheckOpenApiSyncCommand;
use App\Shared\Infrastructure\Console\OpenApiBuilder;
use App\Tests\Shared\TemporaryDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The sync check is a QA gate: if it always succeeded, `make qa` would stay
 * green while docs/openapi.yaml drifts from api-contract/. The command runs
 * against a throwaway project directory with the real builder.
 */
final class CheckOpenApiSyncCommandTest extends TestCase
{
    private TemporaryDirectory $project;

    protected function setUp(): void
    {
        $this->project = new TemporaryDirectory('openapi-sync');

        $this->project->write('api-contract/base.yaml', <<<'YAML'
            openapi: 3.1.0
            info:
              title: Sample
              version: 1.0.0
            YAML);
        $this->project->write('api-contract/schemas/Thing.yaml', <<<'YAML'
            Thing:
              type: object
            YAML);
        $this->writePathFragment('List things');
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testSucceedsWhenTheCommittedFileMatchesTheFragments(): void
    {
        $this->commitCurrentBuild();

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testFailsWhenAFragmentChangedAfterTheBuild(): void
    {
        $this->commitCurrentBuild();
        $this->writePathFragment('List all things');

        $tester = $this->runCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('out of sync', $tester->getDisplay());
    }

    public function testFailsWhenTheCommittedFileIsMissing(): void
    {
        $tester = $this->runCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('does not exist', $tester->getDisplay());
    }

    private function writePathFragment(string $summary): void
    {
        $this->project->write('api-contract/paths/things/list.yaml', <<<YAML
            /things:
              get:
                operationId: listThings
                summary: {$summary}
            YAML);
    }

    private function commitCurrentBuild(): void
    {
        $this->project->write('docs/openapi.yaml', new OpenApiBuilder($this->project->path)->build());
    }

    private function runCommand(): CommandTester
    {
        $tester = new CommandTester(
            new CheckOpenApiSyncCommand(new OpenApiBuilder($this->project->path), $this->project->path),
        );
        $tester->execute([]);

        return $tester;
    }
}
