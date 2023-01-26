<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230125095834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE asset_file_version (id UUID NOT NULL, asset_id UUID NOT NULL, file_id UUID NOT NULL, version_name VARCHAR(20) NOT NULL, context JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9D2DAA055DA1941 ON asset_file_version (asset_id)');
        $this->addSql('CREATE INDEX IDX_9D2DAA0593CB796C ON asset_file_version (file_id)');
        $this->addSql('COMMENT ON COLUMN asset_file_version.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_file_version.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_file_version.file_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset_file_version ADD CONSTRAINT FK_9D2DAA055DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_file_version ADD CONSTRAINT FK_9D2DAA0593CB796C FOREIGN KEY (file_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_file_version DROP CONSTRAINT FK_9D2DAA055DA1941');
        $this->addSql('ALTER TABLE asset_file_version DROP CONSTRAINT FK_9D2DAA0593CB796C');
        $this->addSql('DROP TABLE asset_file_version');
    }
}
