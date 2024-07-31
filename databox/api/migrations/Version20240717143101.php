<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240717143101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attribute_entity (id UUID NOT NULL, workspace_id UUID NOT NULL, type VARCHAR(100) NOT NULL, value TEXT NOT NULL, locale VARCHAR(10) DEFAULT NULL, position INT NOT NULL, translations JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2CC96A3282D40A1F ON attribute_entity (workspace_id)');
        $this->addSql('CREATE INDEX attr_entity_type_idx ON attribute_entity (type)');
        $this->addSql('COMMENT ON COLUMN attribute_entity.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_entity.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_entity.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN attribute_entity.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE attribute_entity ADD CONSTRAINT FK_2CC96A3282D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute_item DROP CONSTRAINT fk_44f3819682d40a1f');
        $this->addSql('DROP TABLE attribute_item');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE attribute_item (id UUID NOT NULL, workspace_id UUID NOT NULL, type VARCHAR(100) NOT NULL, value TEXT NOT NULL, locale VARCHAR(10) DEFAULT NULL, "position" INT NOT NULL, translations JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_44f3819682d40a1f ON attribute_item (workspace_id)');
        $this->addSql('CREATE INDEX item_type_idx ON attribute_item (type)');
        $this->addSql('COMMENT ON COLUMN attribute_item.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_item.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_item.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN attribute_item.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE attribute_item ADD CONSTRAINT fk_44f3819682d40a1f FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute_entity DROP CONSTRAINT FK_2CC96A3282D40A1F');
        $this->addSql('DROP TABLE attribute_entity');
    }
}
