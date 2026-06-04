<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigureDatabaseCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:database:configure');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->connection->executeQuery('CREATE EXTENSION IF NOT EXISTS ltree');

        $output->writeln('Database configured successfully.');

        return Command::SUCCESS;
    }
}
