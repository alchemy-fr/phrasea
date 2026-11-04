<?php

namespace App\Configurator\Vendor\Keycloak\Migrations;

use App\Configurator\Vendor\Keycloak\KeycloakManager;
use App\Util\EnvHelper;
use Doctrine\DBAL\Schema\Schema;

trait KeycloakMigrationTrait
{
    private KeycloakManager $keycloakManager;

    public function setKeycloakManager(KeycloakManager $keycloakManager): void
    {
        $this->keycloakManager = $keycloakManager;
    }

    /**
     * Without Keycloak configuration there is no Keycloak to migrate. A skipped
     * migration is not recorded: it runs once the configuration is enabled.
     */
    public function preUp(Schema $schema): void
    {
        $this->skipIf(
            !EnvHelper::getBooleanEnv('CONFIGURATOR_CONFIGURE_KEYCLOAK', true),
            'Keycloak configuration is disabled by CONFIGURATOR_CONFIGURE_KEYCLOAK',
        );
    }
}
