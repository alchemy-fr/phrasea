<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211110172912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_definition ALTER fallback TYPE TEXT');
        $this->addSql('ALTER TABLE attribute_definition ALTER fallback DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN attribute_definition.fallback IS \'(DC2Type:array)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE attribute_definition ALTER fallback TYPE TEXT');
        $this->addSql('ALTER TABLE attribute_definition ALTER fallback DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN attribute_definition.fallback IS NULL');
    }
}
