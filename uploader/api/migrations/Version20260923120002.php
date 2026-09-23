<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Realign a database built by the migrations with the entity mapping.
 *
 * Found by replaying every migration on an empty database (bin/dev/test-migrations.sh)
 * and running doctrine:schema:validate on the result. Every statement is idempotent:
 * a database created by setup.sh (doctrine:schema:update) is already in this state.
 */
final class Version20260923120002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Realign the migrated schema with the entity mapping';
    }

    public function up(Schema $schema): void
    {
        // FOSOAuthServer is gone: Version20231211163236 dropped the token tables but left the client one.
        $this->addSql('DROP TABLE IF EXISTS oauth_client');

        // Version20220803131308 renamed bulk_data to target_params and Version20220803131440 its
        // index, but the foreign key kept the name derived from the old table name.
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_68fd8f15158e0b66' AND conrelid = 'target_params'::regclass) THEN
                    ALTER TABLE target_params RENAME CONSTRAINT fk_68fd8f15158e0b66 TO fk_86c4e943158e0b66;
                END IF;
            END $$
            SQL);
    }

    public function down(Schema $schema): void
    {
        // The dropped oauth_client table was dead: it is not restored.
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_86c4e943158e0b66' AND conrelid = 'target_params'::regclass) THEN
                    ALTER TABLE target_params RENAME CONSTRAINT fk_86c4e943158e0b66 TO fk_68fd8f15158e0b66;
                END IF;
            END $$
            SQL);
    }
}
