<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512113431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename asset_title_attribute to asset_name_attribute and rename title column to name in several tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_title_attribute DROP CONSTRAINT fk_d86b14d382d40a1f');
        $this->addSql('ALTER TABLE asset_title_attribute DROP CONSTRAINT fk_d86b14d3d11ea911');
        $this->addSql('ALTER TABLE asset_title_attribute RENAME TO asset_name_attribute');
        $this->addSql('ALTER INDEX idx_d86b14d382d40a1f RENAME TO IDX_BCFCF5AE82D40A1F');
        $this->addSql('ALTER INDEX idx_d86b14d3d11ea911 RENAME TO IDX_BCFCF5AED11EA911');
        $this->addSql('ALTER TABLE asset_name_attribute ADD CONSTRAINT FK_BCFCF5AE82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_name_attribute ADD CONSTRAINT FK_BCFCF5AED11EA911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE media_index DROP CONSTRAINT fk_9f6aa3b0514956fd');
        $this->addSql('ALTER TABLE media_index DROP CONSTRAINT fk_9f6aa3b082d40a1f');
        $this->addSql('DROP TABLE media_index');
        $this->addSql('ALTER TABLE asset RENAME COLUMN title TO name');
        $this->addSql('ALTER TABLE asset_data_template RENAME COLUMN title TO asset_name');
        $this->addSql('ALTER TABLE basket RENAME COLUMN title TO name');
        $this->addSql('ALTER TABLE collection RENAME COLUMN title TO name');
        $this->addSql('ALTER TABLE profile RENAME COLUMN title TO name');
        $this->addSql('ALTER TABLE saved_search RENAME COLUMN title TO name');
        $this->addSql('ALTER TABLE share RENAME COLUMN title TO name');
        $this->addSql('DROP INDEX uniq_integration_key');
        $this->addSql('ALTER TABLE workspace_integration RENAME COLUMN title TO name');
        $this->addSql('CREATE UNIQUE INDEX uniq_integration_key ON workspace_integration (workspace_id, name, integration)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE media_index (id UUID NOT NULL, collection_id UUID NOT NULL, workspace_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_9f6aa3b0514956fd ON media_index (collection_id)');
        $this->addSql('CREATE INDEX idx_9f6aa3b082d40a1f ON media_index (workspace_id)');
        $this->addSql('COMMENT ON COLUMN media_index.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN media_index.collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN media_index.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE media_index ADD CONSTRAINT fk_9f6aa3b0514956fd FOREIGN KEY (collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE media_index ADD CONSTRAINT fk_9f6aa3b082d40a1f FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_name_attribute DROP CONSTRAINT FK_BCFCF5AE82D40A1F');
        $this->addSql('ALTER TABLE asset_name_attribute DROP CONSTRAINT FK_BCFCF5AED11EA911');
        $this->addSql('ALTER INDEX IDX_BCFCF5AE82D40A1F RENAME TO idx_d86b14d382d40a1f');
        $this->addSql('ALTER INDEX IDX_BCFCF5AED11EA911 RENAME TO idx_d86b14d3d11ea911');
        $this->addSql('ALTER TABLE asset_name_attribute RENAME TO asset_title_attribute');
        $this->addSql('ALTER TABLE asset_title_attribute ADD CONSTRAINT fk_d86b14d382d40a1f FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_title_attribute ADD CONSTRAINT fk_d86b14d3d11ea911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE share RENAME COLUMN name TO title');
        $this->addSql('ALTER TABLE saved_search RENAME COLUMN name TO title');
        $this->addSql('ALTER TABLE asset_data_template RENAME COLUMN asset_name TO title');
        $this->addSql('DROP INDEX uniq_integration_key');
        $this->addSql('ALTER TABLE workspace_integration RENAME COLUMN name TO title');
        $this->addSql('CREATE UNIQUE INDEX uniq_integration_key ON workspace_integration (workspace_id, title, integration)');
        $this->addSql('ALTER TABLE asset RENAME COLUMN name TO title');
        $this->addSql('ALTER TABLE basket RENAME COLUMN name TO title');
        $this->addSql('ALTER TABLE profile RENAME COLUMN name TO title');
        $this->addSql('ALTER TABLE collection RENAME COLUMN name TO title');
    }
}
