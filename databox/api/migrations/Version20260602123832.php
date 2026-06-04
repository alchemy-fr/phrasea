<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602123832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on asset.created_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX asset_created_at_idx ON asset (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX asset_created_at_idx');
    }
}
