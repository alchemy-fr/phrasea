<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729211325 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add config JSON column to rendition_definition';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendition_definition ADD config JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendition_definition DROP config');
    }
}
