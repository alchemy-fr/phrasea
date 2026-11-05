<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105022500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Region of interest of the assets, and the option of the exports to ignore it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset ADD roi JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_export ADD ignore_roi BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_export DROP ignore_roi');
        $this->addSql('ALTER TABLE asset DROP roi');
    }
}
