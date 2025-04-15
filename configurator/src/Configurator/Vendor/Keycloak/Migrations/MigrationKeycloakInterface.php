<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak\Migrations;

use App\Configurator\Vendor\Keycloak\KeycloakManager;

interface MigrationKeycloakInterface
{
    public function setKeycloakManager(KeycloakManager $keycloakManager): void;
    public function setSymfonyApplications(array $symfonyApplications): void;
    public function setFrontendApplications(array $frontendApplications): void;
}
