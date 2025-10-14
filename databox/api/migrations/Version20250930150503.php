<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250930150503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition ADD target SMALLINT NULL');
        $this->addSql('UPDATE attribute_definition SET "target" = 1');
        $this->addSql('ALTER TABLE attribute_definition ALTER COLUMN "target" SET NOT NULL');

        $this->addSql('ALTER TABLE rendition_definition ADD target SMALLINT NULL');
        $this->addSql('UPDATE rendition_definition SET "target" = 1');
        $this->addSql('ALTER TABLE rendition_definition ALTER COLUMN "target" SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE rendition_definition DROP target');
        $this->addSql('ALTER TABLE attribute_definition DROP target');
    }
}
