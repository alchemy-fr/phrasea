<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Realign a database built by the migrations with the entity mapping.
 *
 * Found by replaying every migration on an empty database (bin/dev/test-migrations.sh)
 * and running doctrine:schema:validate on the result. A database created by setup.sh
 * (doctrine:schema:update) is already in this state.
 */
final class Version20260923120001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Realign the migrated schema with the entity mapping';
    }

    public function up(Schema $schema): void
    {
        // FOSOAuthServer is gone: Version20231211163355 dropped the token tables but left the client one.
        $this->addSql('DROP TABLE IF EXISTS oauth_client');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The dropped oauth_client table was dead, it is not restored.');
    }
}
