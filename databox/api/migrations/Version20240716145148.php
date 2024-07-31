<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240716145148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attribute_item (id UUID NOT NULL, type VARCHAR(100) NOT NULL, value TEXT NOT NULL, locale VARCHAR(10) DEFAULT NULL, position INT NOT NULL, translations JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX item_type_idx ON attribute_item (type)');
        $this->addSql('COMMENT ON COLUMN attribute_item.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_item.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN attribute_item.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE attribute DROP CONSTRAINT fk_fa7aeffbb4491b47');
        $this->addSql('DROP INDEX idx_fa7aeffbb4491b47');
        $this->addSql('ALTER TABLE attribute DROP translation_origin_id');
        $this->addSql('ALTER TABLE attribute DROP translation_id');
        $this->addSql('ALTER TABLE attribute DROP translation_origin_hash');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE attribute_item');
        $this->addSql('ALTER TABLE attribute ADD translation_origin_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD translation_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD translation_origin_hash VARCHAR(32) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN attribute.translation_origin_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute.translation_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT fk_fa7aeffbb4491b47 FOREIGN KEY (translation_origin_id) REFERENCES attribute (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_fa7aeffbb4491b47 ON attribute (translation_origin_id)');
    }
}
