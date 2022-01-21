<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220121130159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alternate_url (id UUID NOT NULL, workspace_id UUID NOT NULL, type VARCHAR(50) NOT NULL, label VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E7B1A0BC82D40A1F ON alternate_url (workspace_id)');
        $this->addSql('COMMENT ON COLUMN alternate_url.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN alternate_url.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE alternate_url ADD CONSTRAINT FK_E7B1A0BC82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE alternate_url');
    }
}
