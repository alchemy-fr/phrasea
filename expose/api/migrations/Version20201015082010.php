<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201015082010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE asset DROP CONSTRAINT fk_2af5a5c9d346bbd');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT fk_2af5a5cea11af98');
        $this->addSql('DROP INDEX idx_2af5a5cea11af98');
        $this->addSql('DROP INDEX idx_2af5a5c9d346bbd');
        $this->addSql('ALTER TABLE asset DROP preview_definition_id');
        $this->addSql('ALTER TABLE asset DROP thumbnail_definition_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset ADD preview_definition_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE asset ADD thumbnail_definition_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN asset.preview_definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset.thumbnail_definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT fk_2af5a5c9d346bbd FOREIGN KEY (preview_definition_id) REFERENCES sub_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT fk_2af5a5cea11af98 FOREIGN KEY (thumbnail_definition_id) REFERENCES sub_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_2af5a5cea11af98 ON asset (thumbnail_definition_id)');
        $this->addSql('CREATE INDEX idx_2af5a5c9d346bbd ON asset (preview_definition_id)');
    }
}
