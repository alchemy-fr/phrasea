<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240115123246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_class ADD labels JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD labels JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE rendition_class ADD labels JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE rendition_definition ADD labels JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE attribute_class DROP labels');
        $this->addSql('ALTER TABLE rendition_class DROP labels');
        $this->addSql('ALTER TABLE attribute_definition DROP labels');
        $this->addSql('ALTER TABLE rendition_definition DROP labels');
    }
}
