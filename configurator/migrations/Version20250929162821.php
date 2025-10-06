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
final class Version20250929162821 extends AbstractMigration implements KeycloakMigrationInterface
{
    use KeycloakMigrationTrait;

    public function getDescription(): string
    {
        return 'Remove rendition-class scopes';
    }

    public function up(Schema $schema): void
    {
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
