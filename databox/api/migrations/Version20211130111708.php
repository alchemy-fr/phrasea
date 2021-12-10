<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211130111708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE attribute_definition SET translatable = false, allow_invalid = false');
        $this->addSql('ALTER TABLE attribute_definition ALTER translatable SET NOT NULL');
        $this->addSql('ALTER TABLE attribute_definition ALTER allow_invalid SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE attribute_definition ALTER translatable DROP NOT NULL');
        $this->addSql('ALTER TABLE attribute_definition ALTER allow_invalid DROP NOT NULL');
    }
}
