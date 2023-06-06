<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230503085950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE workspace_secret (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(100) NOT NULL, value TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6C62394D82D40A1F ON workspace_secret (workspace_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_key ON workspace_secret (workspace_id, name)');
        $this->addSql('COMMENT ON COLUMN workspace_secret.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN workspace_secret.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE workspace_secret ADD CONSTRAINT FK_6C62394D82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE workspace_secret DROP CONSTRAINT FK_6C62394D82D40A1F');
        $this->addSql('DROP TABLE workspace_secret');
    }
}
