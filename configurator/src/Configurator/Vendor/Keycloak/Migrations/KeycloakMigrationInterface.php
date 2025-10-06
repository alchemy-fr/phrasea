<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak\Migrations;

use App\Configurator\Vendor\Keycloak\KeycloakManager;

interface KeycloakMigrationInterface
{
    public function setKeycloakManager(KeycloakManager $keycloakManager): void;
}
