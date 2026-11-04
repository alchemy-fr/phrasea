<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Export of basket items (with their clip)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_export ADD basket_assets JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_export DROP basket_assets');
    }
}
