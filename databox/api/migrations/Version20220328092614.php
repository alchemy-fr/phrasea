<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220328092614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE asset_title_attribute (id UUID NOT NULL, workspace_id UUID NOT NULL, definition_id UUID NOT NULL, priority SMALLINT NOT NULL, overrides BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D86B14D382D40A1F ON asset_title_attribute (workspace_id)');
        $this->addSql('CREATE INDEX IDX_D86B14D3D11EA911 ON asset_title_attribute (definition_id)');
        $this->addSql('COMMENT ON COLUMN asset_title_attribute.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_title_attribute.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_title_attribute.definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset_title_attribute ADD CONSTRAINT FK_D86B14D382D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_title_attribute ADD CONSTRAINT FK_D86B14D3D11EA911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE asset_title_attribute');
    }
}
