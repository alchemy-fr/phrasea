<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211124141441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_rendition DROP CONSTRAINT fk_44fbbc15908e2ffe');
        $this->addSql('DROP INDEX idx_7a972a83908e2ffe');
        $this->addSql('DROP INDEX uniq_representation');
        $this->addSql('ALTER TABLE asset_rendition RENAME COLUMN specification_id TO definition_id');
        $this->addSql('ALTER TABLE asset_rendition ADD CONSTRAINT FK_7A972A83D11EA911 FOREIGN KEY (definition_id) REFERENCES rendition_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_7A972A83D11EA911 ON asset_rendition (definition_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_representation ON asset_rendition (definition_id, asset_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_rendition DROP CONSTRAINT FK_7A972A83D11EA911');
        $this->addSql('DROP INDEX IDX_7A972A83D11EA911');
        $this->addSql('DROP INDEX uniq_representation');
        $this->addSql('ALTER TABLE asset_rendition RENAME COLUMN definition_id TO specification_id');
        $this->addSql('ALTER TABLE asset_rendition ADD CONSTRAINT fk_44fbbc15908e2ffe FOREIGN KEY (specification_id) REFERENCES rendition_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_7a972a83908e2ffe ON asset_rendition (specification_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_representation ON asset_rendition (specification_id, asset_id)');
    }
}
