<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250210130752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition ADD enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE attribute_definition ALTER enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE attribute_definition ADD last_errors JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition DROP enabled');
        $this->addSql('ALTER TABLE attribute_definition DROP last_errors');
    }
}
