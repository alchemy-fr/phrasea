<?php

namespace App\Configurator\Vendor\Keycloak\Migrations;

use App\Configurator\ConfiguratorFlags;
use App\Configurator\Vendor\Keycloak\KeycloakManager;
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
            !ConfiguratorFlags::isEnabled('keycloak'),
            'Keycloak configuration is disabled by CONFIGURATOR_CONFIGURE_KEYCLOAK',
        );
    }
}
