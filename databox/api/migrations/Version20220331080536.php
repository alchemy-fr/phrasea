<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220331080536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute ADD translation_origin_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD translation_origin_hash VARCHAR(32) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN attribute.translation_origin_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFBB4491B47 FOREIGN KEY (translation_origin_id) REFERENCES attribute (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_FA7AEFFBB4491B47 ON attribute (translation_origin_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE attribute DROP CONSTRAINT FK_FA7AEFFBB4491B47');
        $this->addSql('DROP INDEX IDX_FA7AEFFBB4491B47');
        $this->addSql('ALTER TABLE attribute DROP translation_origin_id');
        $this->addSql('ALTER TABLE attribute DROP translation_origin_hash');
    }
}
