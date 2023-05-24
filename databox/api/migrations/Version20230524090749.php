<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230524090749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE workflow_state ADD asset_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE workflow_state ADD initiator_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN workflow_state.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE workflow_state ADD CONSTRAINT FK_12DDA4CF5DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_12DDA4CF5DA1941 ON workflow_state (asset_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE workflow_state DROP CONSTRAINT FK_12DDA4CF5DA1941');
        $this->addSql('DROP INDEX IDX_12DDA4CF5DA1941');
        $this->addSql('ALTER TABLE workflow_state DROP asset_id');
        $this->addSql('ALTER TABLE workflow_state DROP initiator_id');
    }
}
