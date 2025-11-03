<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Configurator\Vendor\Keycloak\KeycloakInterface;
use App\Configurator\Vendor\Keycloak\Migrations\KeycloakMigrationInterface;
use App\Configurator\Vendor\Keycloak\Migrations\KeycloakMigrationTrait;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251014150415 extends AbstractMigration implements KeycloakMigrationInterface
{
    use KeycloakMigrationTrait;

    public function getDescription(): string
    {
        return 'Add application roles';
    }

    public function up(Schema $schema): void
    {
        $adminSubRoles = [];
        $defaultRoles = [];
        foreach ([
            'databox',
            'uploader',
            'expose',
        ] as $app) {
            $defaultRoles[$app] = [
                'description' => sprintf('Access to %s app', ucwords($app)),
            ];

            $adminSubRoles[$app.'-admin'] = [
                'description' => sprintf('Admin access for %s', ucwords($app)),
                'roles' => [
                    $app => $defaultRoles[$app],
                ],
            ];
        }

        $defaultRolesWrapperRole = 'default-roles-'.$this->keycloakManager->getRealmName();
        $roleHierarchy = [
            KeycloakInterface::ROLE_ADMIN => [
                'description' => 'Can do anything',
                'roles' => $adminSubRoles,
            ],
            KeycloakInterface::ROLE_TECH => [
                'description' => 'Access to Dev/Ops tools',
                'roles' => [],
            ],
            $defaultRolesWrapperRole => [
                'roles' => $defaultRoles,
            ],
        ];
        $this->keycloakManager->createRoleHierarchy($roleHierarchy);
    }

    public function down(Schema $schema): void
    {
    }
}
