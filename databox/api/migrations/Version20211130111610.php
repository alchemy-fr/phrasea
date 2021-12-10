<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211130111610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_definition ADD translatable BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD allow_invalid BOOLEAN NULL');
        $this->addSql('ALTER TABLE failed_event ADD error TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE failed_event DROP error');
        $this->addSql('ALTER TABLE attribute_definition DROP translatable');
        $this->addSql('ALTER TABLE attribute_definition DROP allow_invalid');
    }
}
