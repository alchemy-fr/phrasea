<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220831152649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE workspace_integration (id UUID NOT NULL, workspace_id UUID NOT NULL, title VARCHAR(255) NOT NULL, integration VARCHAR(100) NOT NULL, options JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6DBFC78382D40A1F ON workspace_integration (workspace_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_integration_key ON workspace_integration (workspace_id, title, integration)');
        $this->addSql('COMMENT ON COLUMN workspace_integration.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN workspace_integration.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE workspace_integration ADD CONSTRAINT FK_6DBFC78382D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE workspace_integration');
    }
}
