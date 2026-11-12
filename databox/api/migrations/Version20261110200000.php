<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workspace limiting the results of a saved search automation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_search_automation ADD workspace_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE saved_search_automation ADD CONSTRAINT FK_B500460F82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_B500460F82D40A1F ON saved_search_automation (workspace_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_search_automation DROP CONSTRAINT FK_B500460F82D40A1F');
        $this->addSql('DROP INDEX IDX_B500460F82D40A1F');
        $this->addSql('ALTER TABLE saved_search_automation DROP workspace_id');
    }
}
