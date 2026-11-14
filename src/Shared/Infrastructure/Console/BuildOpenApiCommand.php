<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:openapi:build', description: 'Merge api-contract/ fragments into docs/openapi.yaml')]
final class BuildOpenApiCommand extends Command
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

        file_put_contents($outputFile, $this->builder->build());

        $io->success('OpenAPI spec written to docs/openapi.yaml');

        return Command::SUCCESS;
    }
}
