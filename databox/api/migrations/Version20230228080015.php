<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230228080015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE asset_data_template (id UUID NOT NULL, collection_id UUID DEFAULT NULL, workspace_id UUID NOT NULL, name VARCHAR(255) NOT NULL, public BOOLEAN NOT NULL, owner_id VARCHAR(36) NOT NULL, title VARCHAR(255) DEFAULT NULL, data JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9ECB0F3E514956FD ON asset_data_template (collection_id)');
        $this->addSql('CREATE INDEX IDX_9ECB0F3E82D40A1F ON asset_data_template (workspace_id)');
        $this->addSql('COMMENT ON COLUMN asset_data_template.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_data_template.collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_data_template.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE asset_data_template_tag (asset_data_template_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(asset_data_template_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_6AF3D610C39A0AC3 ON asset_data_template_tag (asset_data_template_id)');
        $this->addSql('CREATE INDEX IDX_6AF3D610BAD26311 ON asset_data_template_tag (tag_id)');
        $this->addSql('COMMENT ON COLUMN asset_data_template_tag.asset_data_template_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_data_template_tag.tag_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE template_attribute (id UUID NOT NULL, template_id UUID NOT NULL, definition_id UUID NOT NULL, translation_origin_id UUID DEFAULT NULL, locale VARCHAR(10) DEFAULT NULL, position INT NOT NULL, translation_id UUID DEFAULT NULL, translation_origin_hash VARCHAR(32) DEFAULT NULL, value TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3329994D5DA0FB8 ON template_attribute (template_id)');
        $this->addSql('CREATE INDEX IDX_3329994DD11EA911 ON template_attribute (definition_id)');
        $this->addSql('CREATE INDEX IDX_3329994DB4491B47 ON template_attribute (translation_origin_id)');
        $this->addSql('COMMENT ON COLUMN template_attribute.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN template_attribute.template_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN template_attribute.definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN template_attribute.translation_origin_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN template_attribute.translation_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset_data_template ADD CONSTRAINT FK_9ECB0F3E514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_data_template ADD CONSTRAINT FK_9ECB0F3E82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_data_template_tag ADD CONSTRAINT FK_6AF3D610C39A0AC3 FOREIGN KEY (asset_data_template_id) REFERENCES asset_data_template (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_data_template_tag ADD CONSTRAINT FK_6AF3D610BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE template_attribute ADD CONSTRAINT FK_3329994D5DA0FB8 FOREIGN KEY (template_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE template_attribute ADD CONSTRAINT FK_3329994DD11EA911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE template_attribute ADD CONSTRAINT FK_3329994DB4491B47 FOREIGN KEY (translation_origin_id) REFERENCES template_attribute (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_data_template DROP CONSTRAINT FK_9ECB0F3E514956FD');
        $this->addSql('ALTER TABLE asset_data_template DROP CONSTRAINT FK_9ECB0F3E82D40A1F');
        $this->addSql('ALTER TABLE asset_data_template_tag DROP CONSTRAINT FK_6AF3D610C39A0AC3');
        $this->addSql('ALTER TABLE asset_data_template_tag DROP CONSTRAINT FK_6AF3D610BAD26311');
        $this->addSql('ALTER TABLE template_attribute DROP CONSTRAINT FK_3329994D5DA0FB8');
        $this->addSql('ALTER TABLE template_attribute DROP CONSTRAINT FK_3329994DD11EA911');
        $this->addSql('ALTER TABLE template_attribute DROP CONSTRAINT FK_3329994DB4491B47');
        $this->addSql('DROP TABLE asset_data_template');
        $this->addSql('DROP TABLE asset_data_template_tag');
        $this->addSql('DROP TABLE template_attribute');
    }
}
