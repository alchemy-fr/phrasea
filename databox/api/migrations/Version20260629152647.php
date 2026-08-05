<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629152647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archived_at column to basket table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE basket ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN basket.archived_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE basket DROP archived_at');
    }
}
