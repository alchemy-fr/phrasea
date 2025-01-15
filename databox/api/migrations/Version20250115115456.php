<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250115115456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rendition_definition ADD build_mode SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('UPDATE rendition_definition SET build_mode = 0 WHERE pick_source_file = false AND definition = \'\'');
        $this->addSql('UPDATE rendition_definition SET build_mode = 1 WHERE pick_source_file = true');
        $this->addSql('UPDATE rendition_definition SET build_mode = 2 WHERE pick_source_file = false AND definition != \'\'');
        $this->addSql('ALTER TABLE rendition_definition DROP pick_source_file');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE rendition_definition ADD pick_source_file BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE rendition_definition DROP build_mode');
    }
}
