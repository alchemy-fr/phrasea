<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Command;

use Alchemy\CoreBundle\Command\LifecycleStateCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class LifecycleStateCommandTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public static function tableNameProvider(): array
    {
        return [
            'default table' => [null, 'doctrine_migration_versions'],
            'custom table' => ['migration_versions', 'migration_versions'],
        ];
    }

    #[DataProvider('tableNameProvider')]
    public function testFreshWithoutMigrationsTable(?string $configuredTable, string $table): void
    {
        $this->assertState(LifecycleStateCommand::STATE_FRESH, $configuredTable);
    }

    #[DataProvider('tableNameProvider')]
    public function testFreshWithEmptyMigrationsTable(?string $configuredTable, string $table): void
    {
        $this->createMigrationsTable($table);

        $this->assertState(LifecycleStateCommand::STATE_FRESH, $configuredTable);
    }

    #[DataProvider('tableNameProvider')]
    public function testInstalledWithRecordedMigration(?string $configuredTable, string $table): void
    {
        $this->createMigrationsTable($table);
        $this->connection->insert($table, [
            'version' => 'DoctrineMigrations\\Version20240101000000',
        ]);

        $this->assertState(LifecycleStateCommand::STATE_INSTALLED, $configuredTable);
    }

    public function testIgnoresAnotherMigrationsTable(): void
    {
        $this->createMigrationsTable('doctrine_migration_versions');
        $this->connection->insert('doctrine_migration_versions', [
            'version' => 'DoctrineMigrations\\Version20240101000000',
        ]);

        $this->assertState(LifecycleStateCommand::STATE_FRESH, 'migration_versions');
    }

    private function createMigrationsTable(string $table): void
    {
        $this->connection->executeStatement(sprintf(
            'CREATE TABLE %s (version VARCHAR(191) NOT NULL PRIMARY KEY, executed_at DATETIME DEFAULT NULL, execution_time INTEGER DEFAULT NULL)',
            $table,
        ));
    }

    private function assertState(string $expected, ?string $configuredTable): void
    {
        $configuration = new Configuration();
        if (null !== $configuredTable) {
            $storage = new TableMetadataStorageConfiguration();
            $storage->setTableName($configuredTable);
            $configuration->setMetadataStorageConfiguration($storage);
        }

        $dependencyFactory = DependencyFactory::fromConnection(
            new ExistingConfiguration($configuration),
            new ExistingConnection($this->connection),
        );

        $tester = new CommandTester(new LifecycleStateCommand($dependencyFactory));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame($expected, trim($tester->getDisplay()));
    }
}
