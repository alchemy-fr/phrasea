<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Composite indexes matching the ORDER BY of the Elasticsearch populate pagers (asset: created_at DESC, id), so that each page is served by an index scan instead of a full sort';
    }

    /**
     * CREATE INDEX CONCURRENTLY cannot run inside a transaction; it avoids locking
     * writes on these large tables while the index is built.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS asset_created_at_id_idx ON asset (created_at DESC, id ASC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS asset_created_at_id_idx');
    }
}
