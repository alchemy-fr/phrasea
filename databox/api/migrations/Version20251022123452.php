<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251022123452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE asset_attachment (id UUID NOT NULL, asset_id UUID NOT NULL, file_id UUID NOT NULL, name VARCHAR(100) DEFAULT NULL, priority SMALLINT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, extra_metadata JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BFBE3AE15DA1941 ON asset_attachment (asset_id)');
        $this->addSql('CREATE INDEX IDX_BFBE3AE193CB796C ON asset_attachment (file_id)');
        $this->addSql('COMMENT ON COLUMN asset_attachment.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_attachment.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_attachment.file_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_attachment.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_attachment.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE asset_attachment ADD CONSTRAINT FK_BFBE3AE15DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_attachment ADD CONSTRAINT FK_BFBE3AE193CB796C FOREIGN KEY (file_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_attachment DROP CONSTRAINT FK_BFBE3AE15DA1941');
        $this->addSql('ALTER TABLE asset_attachment DROP CONSTRAINT FK_BFBE3AE193CB796C');
        $this->addSql('DROP TABLE asset_attachment');
    }
}
