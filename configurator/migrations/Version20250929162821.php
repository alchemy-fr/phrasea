<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Configurator\Vendor\Keycloak\KeycloakManager;
use App\Configurator\Vendor\Keycloak\Migrations\MigrationKeycloakInterface;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250929162821 extends AbstractMigration implements MigrationKeycloakInterface
{
    private KeycloakManager $keycloakManager;

    public function setKeycloakManager(KeycloakManager $keycloakManager): void
    {
        $this->keycloakManager = $keycloakManager;
    }

    public function setSymfonyApplications(array $symfonyApplications): void
    {
    }

    public function setFrontendApplications(array $frontendApplications): void
    {
    }

    public function getDescription(): string
    {
        return 'Add redirectAfterPasswordUpdate attribute to Keycloak clients';
    }

    public function up(Schema $schema): void
    {
        $appScopes = [
            'databox' => \array_merge([
                'super-admin',
            ], ...\array_map(fn (string $ns): array => \array_map(fn (string $p): string => $ns.':'.$p, [
                'create',
                'list',
                'read',
                'edit',
                'delete',
                'operator',
                'owner',
            ]), [
                'asset-data-template',
                'asset',
                'attribute-entity',
                'attribute-list',
                'attribute-policy',
                'basket',
                'collection',
                'entity-list',
                'rendition-definition',
                'rendition-policy',
                'rendition-class',
                'rendition-rule',
                'rendition',
                'integration',
                'workspace',
            ])),
            'expose' => [
                'publish',
            ],
            'uploader' => [
                'commit:list',
            ],
        ];

        foreach ([
            'databox',
            'expose',
            'uploader',
        ] as $app) {
            $clientId = getenv(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app)));

            $clientData = $this->keycloakManager->getClientByClientId($clientId);
            foreach ($appScopes[$app] ?? [] as $scope) {
                $this->keycloakManager->removeScopeFromClient($scope, $clientData['id']);
            }
        }

        foreach ([
            'create',
            'list',
            'read',
            'edit',
            'delete',
            'operator',
            'owner',
        ] as $perm) {
            $this->keycloakManager->deleteScope('rendition-class:'.$perm);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
