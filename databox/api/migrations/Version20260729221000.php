<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729221000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add read_from_metadata and write_metadata JSON columns to attribute_definition';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition ADD read_from_metadata JSON DEFAULT NULL, ADD write_metadata JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition DROP read_from_metadata, DROP write_metadata');
    }
}
