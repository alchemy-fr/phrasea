<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230123142852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE asset_relationship (id UUID NOT NULL, source_id UUID DEFAULT NULL, target_id UUID DEFAULT NULL, type VARCHAR(20) NOT NULL, sticky BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5953845D953C1C61 ON asset_relationship (source_id)');
        $this->addSql('CREATE INDEX IDX_5953845D158E0B66 ON asset_relationship (target_id)');
        $this->addSql('COMMENT ON COLUMN asset_relationship.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_relationship.source_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_relationship.target_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset_relationship ADD CONSTRAINT FK_5953845D953C1C61 FOREIGN KEY (source_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_relationship ADD CONSTRAINT FK_5953845D158E0B66 FOREIGN KEY (target_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT fk_2af5a5c93cb796c');
        $this->addSql('DROP INDEX idx_2af5a5c93cb796c');
        $this->addSql('ALTER TABLE asset RENAME COLUMN file_id TO source_id');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C953C1C61 FOREIGN KEY (source_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_2AF5A5C953C1C61 ON asset (source_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_relationship DROP CONSTRAINT FK_5953845D953C1C61');
        $this->addSql('ALTER TABLE asset_relationship DROP CONSTRAINT FK_5953845D158E0B66');
        $this->addSql('DROP TABLE asset_relationship');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C953C1C61');
        $this->addSql('DROP INDEX IDX_2AF5A5C953C1C61');
        $this->addSql('ALTER TABLE asset RENAME COLUMN source_id TO file_id');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT fk_2af5a5c93cb796c FOREIGN KEY (file_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_2af5a5c93cb796c ON asset (file_id)');
    }
}
