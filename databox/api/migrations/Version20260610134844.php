<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610134844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop asset_name_attribute table and add name_priority and fill_from_name to attribute_definition';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_name_attribute DROP CONSTRAINT fk_bcfcf5ae82d40a1f');
        $this->addSql('ALTER TABLE asset_name_attribute DROP CONSTRAINT fk_bcfcf5aed11ea911');
        $this->addSql('DROP TABLE asset_name_attribute');
        $this->addSql('ALTER TABLE attribute_definition ADD name_priority SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD fill_from_name BOOLEAN NOT NULL');
        $this->addSql('CREATE INDEX fill_from_name_idx ON attribute_definition (workspace_id, fill_from_name)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('This migration cannot be reversed because it drops the asset_name_attribute table and its data.');
    }
}
