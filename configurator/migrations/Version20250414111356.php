<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use App\Configurator\Vendor\Keycloak\KeycloakManager;
use App\Configurator\Vendor\Keycloak\Migrations\MigrationKeycloakInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250414111356 extends AbstractMigration implements MigrationKeycloakInterface
{
    private KeycloakManager $keycloakManager;
    private array $symfonyApplications;
    private array $frontendApplications;

    public function setKeycloakManager(KeycloakManager $keycloakManager): void
    {
        $this->keycloakManager = $keycloakManager;
    }

    public function setSymfonyApplications(array $symfonyApplications): void
    {
        $this->symfonyApplications = $symfonyApplications;
    }

    public function setFrontendApplications(array $frontendApplications): void
    {
        $this->frontendApplications = $frontendApplications;
    }
  
    public function getDescription(): string
    {
        return 'Add redirectAfterPasswordUpdate attribute to Keycloak clients';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->symfonyApplications as $app) {
            $clientId = getenv(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app)));
            $rootUrl = getenv(sprintf('%s_API_URL', strtoupper($app)));
            $realm = getenv('KEYCLOAK_REALM_NAME');

            $this->keycloakManager->updateClientByClientId(
                $clientId,
                $realm,
                [
                    'attributes' => [
                        'redirectAfterPasswordUpdate' =>  $rootUrl .'/admin'
                    ],
                ]
            );
       }

       foreach ($this->frontendApplications as $app) {
            $clientId = getenv(sprintf('%s_CLIENT_ID', strtoupper($app)));
            $rootUrl = getenv(sprintf('%s_CLIENT_URL', strtoupper($app)));
            $realm = getenv('KEYCLOAK_REALM_NAME');

            $this->keycloakManager->updateClientByClientId(
                $clientId,
                $realm,
                [
                    'attributes' => [
                        'redirectAfterPasswordUpdate' => $rootUrl
                    ],
                ]
            );
       }
    }

    public function down(Schema $schema): void
    {   
    }
}
