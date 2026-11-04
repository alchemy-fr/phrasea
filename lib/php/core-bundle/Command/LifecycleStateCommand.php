<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Command;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tells bin/lifecycle.sh whether the application database is already installed.
 *
 * "installed" as soon as one migration is recorded, "fresh" otherwise: the
 * destructive install path (schema creation, migrations marked as executed)
 * must only ever run against a database without any recorded migration.
 *
 * The connection and the table are the ones of the Doctrine migrations
 * configuration (the table name differs between the applications). The
 * database must exist (doctrine:database:create runs first).
 */
#[AsCommand('alchemy:lifecycle:state', description: 'Print "installed" or "fresh" depending on the recorded Doctrine migrations')]
class LifecycleStateCommand extends Command
{
    public const string STATE_INSTALLED = 'installed';
    public const string STATE_FRESH = 'fresh';

    public function __construct(
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private readonly DependencyFactory $dependencyFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->getState());

        return Command::SUCCESS;
    }

    public function getState(): string
    {
        $connection = $this->dependencyFactory->getConnection();
        $table = $this->getMigrationsTableName();

        if (!$connection->createSchemaManager()->tablesExist([$table])) {
            return self::STATE_FRESH;
        }

        $count = (int) $connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $connection->quoteSingleIdentifier($table)));

        return $count > 0 ? self::STATE_INSTALLED : self::STATE_FRESH;
    }

    private function getMigrationsTableName(): string
    {
        $storage = $this->dependencyFactory->getConfiguration()->getMetadataStorageConfiguration();
        if (!$storage instanceof TableMetadataStorageConfiguration) {
            $storage = new TableMetadataStorageConfiguration();
        }

        return $storage->getTableName();
    }
}
