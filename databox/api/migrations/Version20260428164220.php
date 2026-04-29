<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428164220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add creator_id and data fields to attribute_entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity ADD creator_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity DROP creator_id');
        $this->addSql('ALTER TABLE attribute_entity DROP data');
    }
}
