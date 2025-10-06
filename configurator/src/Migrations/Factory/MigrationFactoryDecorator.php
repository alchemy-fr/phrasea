<?php

declare(strict_types=1);

namespace App\Migrations\Factory;

use App\Configurator\Vendor\Keycloak\KeycloakManager;
use App\Configurator\Vendor\Keycloak\Migrations\KeycloakMigrationInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Psr\Log\LoggerInterface;

final readonly class MigrationFactoryDecorator implements MigrationFactory
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private KeycloakManager $keycloakManager)
    {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $instance = new $migrationClassName($this->connection, $this->logger);

        if ($instance instanceof KeycloakMigrationInterface) {
            $instance->setKeycloakManager($this->keycloakManager);
        }

        return $instance;
    }
}
