<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020130052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parade: parade and parade_tenant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parade (id UUID NOT NULL, name VARCHAR(100) NOT NULL, url VARCHAR(255) DEFAULT NULL, enabled BOOLEAN NOT NULL, keycloak_groups JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE parade_tenant (id UUID NOT NULL, slug VARCHAR(63) NOT NULL, name VARCHAR(100) NOT NULL, sub_roles BOOLEAN NOT NULL, keycloak_groups JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX parade_tenant_slug_uniq ON parade_tenant (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE parade');
        $this->addSql('DROP TABLE parade_tenant');
    }
}
