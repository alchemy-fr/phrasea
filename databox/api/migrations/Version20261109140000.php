<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Limit of assets handled per run of the saved search automations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_search_automation ADD max_assets_per_run INT DEFAULT 1000 NOT NULL');
        $this->addSql('ALTER TABLE automation_run ADD limit_reached BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE automation_run DROP limit_reached');
        $this->addSql('ALTER TABLE saved_search_automation DROP max_assets_per_run');
    }
}
