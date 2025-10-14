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
final class Version20251006090850 extends AbstractMigration implements KeycloakMigrationInterface
{
    use KeycloakMigrationTrait;

    public function getDescription(): string
    {
        return 'Fix permissions on scopes';
    }

    public function up(Schema $schema): void
    {
        $appScopes = [
            'databox' => array_merge([
                'super-admin',
            ], ...array_map(fn (string $ns): array => array_map(fn (string $p): string => $ns.':'.$p, [
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
                'rendition-rule',
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
            'uploader',
            'expose',
        ] as $app) {
            $clientId = getenv(sprintf('%s_ADMIN_CLIENT_ID', strtoupper($app)));
            $clientData = $this->keycloakManager->getClientByClientId($clientId);

            $roleName = sprintf('%s-admin', $app);
            $this->keycloakManager->addServiceAccountRealmRole($clientData, $roleName);

            $this->keycloakManager->addServiceAccountClientRole($clientData, 'view-users', 'realm-management');
            foreach ($appScopes[$app] ?? [] as $scope) {
                $this->keycloakManager->addScopeToClient($scope, $clientData['id'], false);
            }
        }
    }

    public function down(Schema $schema): void
    {
    }
}
