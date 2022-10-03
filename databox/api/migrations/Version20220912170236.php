<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220912170236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE integration_data (id UUID NOT NULL, integration_id UUID NOT NULL, asset_id UUID DEFAULT NULL, name VARCHAR(100) NOT NULL, value TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_986DCE789E82DDEA ON integration_data (integration_id)');
        $this->addSql('CREATE INDEX IDX_986DCE785DA1941 ON integration_data (asset_id)');
        $this->addSql('CREATE INDEX name ON integration_data (integration_id, asset_id, name)');
        $this->addSql('COMMENT ON COLUMN integration_data.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.integration_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE integration_data ADD CONSTRAINT FK_986DCE789E82DDEA FOREIGN KEY (integration_id) REFERENCES workspace_integration (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_data ADD CONSTRAINT FK_986DCE785DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE integration_data');
    }
}
