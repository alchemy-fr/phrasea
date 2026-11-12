<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Share links filled by the saved search automations, one per workspace';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE automation_share (id UUID NOT NULL, automation_id UUID NOT NULL, workspace_id UUID NOT NULL, share_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX automation_share_ws_uniq ON automation_share (automation_id, workspace_id)');
        $this->addSql('CREATE INDEX IDX_BA726398D1C5DDC3 ON automation_share (automation_id)');
        $this->addSql('CREATE INDEX IDX_BA72639882D40A1F ON automation_share (workspace_id)');
        $this->addSql('CREATE INDEX IDX_BA7263982AE63FDB ON automation_share (share_id)');
        $this->addSql('ALTER TABLE automation_share ADD CONSTRAINT FK_BA726398D1C5DDC3 FOREIGN KEY (automation_id) REFERENCES saved_search_automation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE automation_share ADD CONSTRAINT FK_BA72639882D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE automation_share ADD CONSTRAINT FK_BA7263982AE63FDB FOREIGN KEY (share_id) REFERENCES share (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE automation_share DROP CONSTRAINT FK_BA726398D1C5DDC3');
        $this->addSql('ALTER TABLE automation_share DROP CONSTRAINT FK_BA72639882D40A1F');
        $this->addSql('ALTER TABLE automation_share DROP CONSTRAINT FK_BA7263982AE63FDB');
        $this->addSql('DROP TABLE automation_share');
    }
}
