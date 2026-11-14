<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:openapi:check-sync', description: 'Verify that docs/openapi.yaml is in sync with api-contract/')]
final class CheckOpenApiSyncCommand extends Command
{
    public function __construct(
        private readonly OpenApiBuilder $builder,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputFile = $this->projectDir.'/docs/openapi.yaml';

        if (!file_exists($outputFile)) {
            $io->error('docs/openapi.yaml does not exist. Run "composer app:openapi:build" first.');

            return Command::FAILURE;
        }

        $committed = file_get_contents($outputFile);
        $generated = $this->builder->build();

        if ($generated === $committed) {
            $io->success('docs/openapi.yaml is in sync with api-contract/');

            return Command::SUCCESS;
        }

        $io->error([
            'docs/openapi.yaml is out of sync with api-contract/.',
            'Run "composer app:openapi:build" to regenerate it.',
        ]);

        return Command::FAILURE;
    }
}
