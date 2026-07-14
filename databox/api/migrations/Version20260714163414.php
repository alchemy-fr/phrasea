<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714163414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create asset_export table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE asset_export (id UUID NOT NULL, status SMALLINT NOT NULL, owner_id VARCHAR(36) NOT NULL, user_data JSON NOT NULL, path VARCHAR(255) DEFAULT NULL, assets JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN asset_export.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_export.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_export.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE asset_export');
    }
}
