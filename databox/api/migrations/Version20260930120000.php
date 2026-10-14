<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Number the runs of a workflow on an asset (Ingest #1, #2...)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workflow_state ADD number INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE workflow_state w
            SET number = r.number
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY asset_id, name ORDER BY started_at, id) AS number
                FROM workflow_state
                WHERE asset_id IS NOT NULL
            ) r
            WHERE w.id = r.id
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workflow_state DROP number');
    }
}
