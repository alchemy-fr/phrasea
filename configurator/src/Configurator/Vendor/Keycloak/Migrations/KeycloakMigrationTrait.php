<?php

namespace App\Configurator\Vendor\Keycloak\Migrations;

use App\Configurator\Vendor\Keycloak\KeycloakManager;

trait KeycloakMigrationTrait
{
    private KeycloakManager $keycloakManager;

    public function setKeycloakManager(KeycloakManager $keycloakManager): void
    {
        $this->keycloakManager = $keycloakManager;
    }
}
