<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019102514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Saved search automations: saved_search_automation, automation_run and the cursor automation_seen_asset';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE automation_run (id UUID NOT NULL, trigger_type VARCHAR(20) NOT NULL, dry_run BOOLEAN NOT NULL, initiator_id VARCHAR(36) DEFAULT NULL, status VARCHAR(20) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, matched_count INT NOT NULL, new_count INT NOT NULL, processed_count INT NOT NULL, failed_count INT NOT NULL, action_results JSON NOT NULL, error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, automation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX automation_run_list_idx ON automation_run (automation_id, created_at)');
        $this->addSql('CREATE INDEX IDX_391E9A84D1C5DDC3 ON automation_run (automation_id)');
        $this->addSql('CREATE TABLE automation_seen_asset (first_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, run_id VARCHAR(36) DEFAULT NULL, automation_id UUID NOT NULL, asset_id UUID NOT NULL, PRIMARY KEY (automation_id, asset_id))');
        $this->addSql('CREATE INDEX IDX_F38FAFB0D1C5DDC3 ON automation_seen_asset (automation_id)');
        $this->addSql('CREATE INDEX IDX_F38FAFB05DA1941 ON automation_seen_asset (asset_id)');
        $this->addSql('CREATE TABLE saved_search_automation (id UUID NOT NULL, name VARCHAR(255) NOT NULL, enabled BOOLEAN NOT NULL, schedule VARCHAR(100) DEFAULT NULL, timezone VARCHAR(64) DEFAULT \'UTC\' NOT NULL, scope VARCHAR(10) NOT NULL, actions JSON NOT NULL, next_run_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_run_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_run_status VARCHAR(20) DEFAULT NULL, consecutive_failures SMALLINT DEFAULT 0 NOT NULL, created_by VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, saved_search_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX ssa_due_idx ON saved_search_automation (enabled, next_run_at)');
        $this->addSql('CREATE INDEX IDX_B500460F50DC2954 ON saved_search_automation (saved_search_id)');
        $this->addSql('ALTER TABLE automation_run ADD CONSTRAINT FK_391E9A84D1C5DDC3 FOREIGN KEY (automation_id) REFERENCES saved_search_automation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE automation_seen_asset ADD CONSTRAINT FK_F38FAFB0D1C5DDC3 FOREIGN KEY (automation_id) REFERENCES saved_search_automation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE automation_seen_asset ADD CONSTRAINT FK_F38FAFB05DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE saved_search_automation ADD CONSTRAINT FK_B500460F50DC2954 FOREIGN KEY (saved_search_id) REFERENCES saved_search (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE automation_run DROP CONSTRAINT FK_391E9A84D1C5DDC3');
        $this->addSql('ALTER TABLE automation_seen_asset DROP CONSTRAINT FK_F38FAFB0D1C5DDC3');
        $this->addSql('ALTER TABLE automation_seen_asset DROP CONSTRAINT FK_F38FAFB05DA1941');
        $this->addSql('ALTER TABLE saved_search_automation DROP CONSTRAINT FK_B500460F50DC2954');
        $this->addSql('DROP TABLE automation_run');
        $this->addSql('DROP TABLE automation_seen_asset');
        $this->addSql('DROP TABLE saved_search_automation');
    }
}
