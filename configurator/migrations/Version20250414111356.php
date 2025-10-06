<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Configurator\Vendor\Keycloak\Migrations\KeycloakMigrationInterface;
use App\Configurator\Vendor\Keycloak\Migrations\KeycloakMigrationTrait;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250414111356 extends AbstractMigration implements KeycloakMigrationInterface
{
    use KeycloakMigrationTrait;

    public function getDescription(): string
    {
        return 'Add redirectAfterPasswordUpdate attribute to Keycloak clients';
    }

    public function up(Schema $schema): void
    {
        foreach ([
            'databox',
            'uploader',
            'expose',
        ] as $app) {
            $clientId = getenv(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app)));
            $rootUrl = getenv(sprintf('%s_API_URL', strtoupper($app)));

            $this->keycloakManager->updateClientByClientId(
                $clientId,
                [
                    'attributes' => [
                        'redirectAfterPasswordUpdate' => $rootUrl.'/admin',
                    ],
                ]
            );
        }

        foreach ([
            'databox',
            'expose',
            'uploader',
            'dashboard',
        ] as $app) {
            $clientId = getenv(sprintf('%s_CLIENT_ID', strtoupper($app)));
            $rootUrl = getenv(sprintf('%s_CLIENT_URL', strtoupper($app)));

            $this->keycloakManager->updateClientByClientId(
                $clientId,
                [
                    'attributes' => [
                        'redirectAfterPasswordUpdate' => $rootUrl,
                    ],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
    }
}
