<?php

declare(strict_types=1);

namespace App\Migrations\Factory;

use App\Configurator\Vendor\Keycloak\KeycloakManager;
use App\Configurator\Vendor\Keycloak\Migrations\MigrationKeycloakInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Psr\Log\LoggerInterface;

class MigrationFactoryDecorator implements MigrationFactory
{
    private $keycloakManager;

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private array $symfonyApplications,
        private array $frontendApplications,
        KeycloakManager $keycloakManager)
    {
        $this->keycloakManager = $keycloakManager;
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $instance = new $migrationClassName($this->connection, $this->logger);

        if ($instance instanceof MigrationKeycloakInterface) {
            $instance->setKeycloakManager($this->keycloakManager);
            $instance->setSymfonyApplications($this->symfonyApplications);
            $instance->setFrontendApplications($this->frontendApplications);
        }

        return $instance;
    }
}
