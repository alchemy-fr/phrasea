<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126120500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the exports by creation date (latest exports of the admin home page)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX asset_export_created_at ON asset_export (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX asset_export_created_at');
    }
}
