<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rebuilds the database of the current environment from scratch.
 *
 * Adapted from the reference project, which drops and re-creates the whole database.
 * DBAL cannot do that for SQLite, so all tables are dropped through the schema
 * manager instead, including tables without an entity (migration versions, message
 * queue). This works on every platform; SQLite creates the database file on the
 * first connection.
 */
#[AsCommand(name: 'app:db:reset', description: 'Drop all tables, run the migrations and set up the message transports (dev and test only)')]
final class ResetDatabaseCommand extends Command
{
    private const array ALLOWED_ENVIRONMENTS = ['dev', 'test'];

    private const array STEPS = [
        'doctrine:migrations:migrate' => ['--allow-no-migration' => true],
        // Re-creates the message queue table, so a running worker finds it again.
        'messenger:setup-transports' => [],
    ];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\in_array($this->environment, self::ALLOWED_ENVIRONMENTS, true)) {
            $io->error('This command can only be run in the dev and test environments.');

            return Command::FAILURE;
        }

        $application = $this->getApplication() ?? throw new \LogicException('The command is not attached to an application.');

        $io->section('Dropping all tables');
        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->dropSchemaObjects($schemaManager->introspectSchema());

        foreach (self::STEPS as $name => $arguments) {
            $io->section($name);

            $stepInput = new ArrayInput($arguments);
            $stepInput->setInteractive(false);

            $code = $application->find($name)->run($stepInput, $output);
            if (Command::SUCCESS !== $code) {
                $io->error(\sprintf('Step "%s" failed with exit code %d.', $name, $code));

                return Command::FAILURE;
            }
        }

        $io->success(\sprintf('Database of the "%s" environment has been reset.', $this->environment));

        return Command::SUCCESS;
    }
}
