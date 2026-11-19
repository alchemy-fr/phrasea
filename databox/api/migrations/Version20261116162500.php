<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116162500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep a single "everyone" (NULL user) row per collection in collection_access';
    }

    public function up(Schema $schema): void
    {
        // The NULL user rows may be stale (never purged by the former recomputation):
        // they come back with "bin/console app:collection:recompute-access".
        $this->addSql('DELETE FROM collection_access WHERE user_id IS NULL');
        $this->addSql('CREATE UNIQUE INDEX collection_access_null_user_uniq ON collection_access (collection_id) WHERE (user_id IS NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX collection_access_null_user_uniq');
    }
}
