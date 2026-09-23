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
final class Version20260923120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Realign the migrated schema with the entity mapping';
    }

    public function up(Schema $schema): void
    {
        // FOSOAuthServer is gone: Version20230725154901 dropped the token tables but left the client one.
        $this->addSql('DROP TABLE IF EXISTS oauth_client');

        // Version20211210162319 added asset_rendition.uri; the entity never had this column.
        $this->addSql('ALTER TABLE asset_rendition DROP COLUMN IF EXISTS uri');

        // Column defaults introduced to backfill existing rows, not declared in the mapping.
        $this->addSql('ALTER TABLE asset_rendition ALTER locked DROP DEFAULT');
        $this->addSql('ALTER TABLE asset_rendition ALTER substituted DROP DEFAULT');
        $this->addSql('ALTER TABLE job_state ALTER number DROP DEFAULT');

        // Version20260907230000 was hand-written: type comments were missing and the
        // index/constraint names differ from the ones Doctrine derives from the mapping.
        $this->addSql('COMMENT ON COLUMN file_overridden_metadata.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN file_overridden_metadata.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN file_overridden_metadata.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN file.overridden_metadata_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_definition_write_rendition.attribute_definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_definition_write_rendition.rendition_definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER INDEX IF EXISTS idx_adwr_attribute RENAME TO idx_2225e6197492f274');
        $this->addSql('ALTER INDEX IF EXISTS idx_adwr_rendition RENAME TO idx_2225e6199373edff');
        $this->addSql('ALTER INDEX IF EXISTS uniq_8c9f361019a0ab55 RENAME TO uniq_8c9f36101e268798');
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_8c9f361019a0ab55' AND conrelid = 'file'::regclass) THEN
                    ALTER TABLE file RENAME CONSTRAINT fk_8c9f361019a0ab55 TO fk_8c9f36101e268798;
                END IF;
            END $$
            SQL);

        // Version20260525163114 created a GiST index (gist_path_idx) next to the mapping's btree
        // ca_path_idx on the same ltree column. The mapping cannot express the access method, so
        // keep a single index under the mapping's name, as GiST (it serves the ltree operators
        // and the equality/ordering ones).
        $this->addSql('DROP INDEX IF EXISTS gist_path_idx');
        $this->addSql('DROP INDEX IF EXISTS ca_path_idx');
        $this->addSql('CREATE INDEX ca_path_idx ON collection_access USING GIST (path)');
    }

    public function down(Schema $schema): void
    {
        // The dropped table and column were dead: they are not restored.
        $this->addSql('DROP INDEX IF EXISTS ca_path_idx');
        $this->addSql('CREATE INDEX ca_path_idx ON collection_access (path)');
        $this->addSql('CREATE INDEX gist_path_idx ON collection_access USING GIST (path)');
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_8c9f36101e268798' AND conrelid = 'file'::regclass) THEN
                    ALTER TABLE file RENAME CONSTRAINT fk_8c9f36101e268798 TO fk_8c9f361019a0ab55;
                END IF;
            END $$
            SQL);
        $this->addSql('ALTER INDEX IF EXISTS uniq_8c9f36101e268798 RENAME TO uniq_8c9f361019a0ab55');
        $this->addSql('ALTER INDEX IF EXISTS idx_2225e6199373edff RENAME TO idx_adwr_rendition');
        $this->addSql('ALTER INDEX IF EXISTS idx_2225e6197492f274 RENAME TO idx_adwr_attribute');
        $this->addSql('ALTER TABLE job_state ALTER number SET DEFAULT 0');
        $this->addSql('ALTER TABLE asset_rendition ALTER substituted SET DEFAULT FALSE');
        $this->addSql('ALTER TABLE asset_rendition ALTER locked SET DEFAULT FALSE');
    }
}
