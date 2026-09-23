<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Day-0 migration: the schema as it was before the first recorded migration (Version20200506111259).
 *
 * The first migration was an empty marker: the initial schema had been created with
 * `doctrine:schema:update` and never captured, so the migrations could not be replayed
 * on an empty database (bin/dev/test-migrations.sh). This is the output of
 * `doctrine:schema:create --dump-sql` on the mapping at commit b9f757599 (2020-05-05).
 *
 * On an existing database (setup.sh already ran `doctrine:schema:update` and marked
 * every version as executed, or the app was upgraded through migrate.sh) the tables
 * already exist: this migration is then recorded without running anything.
 */
final class Version20200506000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Day-0: base schema preceding the first migration';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->createSchemaManager()->tablesExist(['asset_commit'])) {
            $this->warnIf(true, 'Base schema already present, nothing to do.');

            return;
        }

        $this->addSql('CREATE TABLE bulk_data (id UUID NOT NULL, data JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN bulk_data.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bulk_data.data IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE TABLE asset (id UUID NOT NULL, commit_id UUID DEFAULT NULL, path VARCHAR(255) NOT NULL, size BIGINT NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2AF5A5C3D5814AC ON asset (commit_id)');
        $this->addSql('COMMENT ON COLUMN asset.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset.commit_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE asset_commit (id UUID NOT NULL, total_size BIGINT NOT NULL, form_data JSON NOT NULL, user_id VARCHAR(255) NOT NULL, token VARCHAR(255) NOT NULL, acknowledged BOOLEAN NOT NULL, notify_email VARCHAR(255) DEFAULT NULL, locale VARCHAR(5) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN asset_commit.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_commit.form_data IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE TABLE failed_event (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(150) NOT NULL, payload JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN failed_event.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN failed_event.payload IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE TABLE form_schema (id UUID NOT NULL, locale VARCHAR(5) DEFAULT NULL, data TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BA6095FC4180C698 ON form_schema (locale)');
        $this->addSql('COMMENT ON COLUMN form_schema.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE access_token (id UUID NOT NULL, client_id VARCHAR(80) NOT NULL, token VARCHAR(255) NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6A2DD685F37A13B ON access_token (token)');
        $this->addSql('CREATE INDEX IDX_B6A2DD6819EB6921 ON access_token (client_id)');
        $this->addSql('COMMENT ON COLUMN access_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE refresh_token (id UUID NOT NULL, client_id VARCHAR(80) NOT NULL, token VARCHAR(255) NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C74F21955F37A13B ON refresh_token (token)');
        $this->addSql('CREATE INDEX IDX_C74F219519EB6921 ON refresh_token (client_id)');
        $this->addSql('COMMENT ON COLUMN refresh_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE oauth_client (id VARCHAR(80) NOT NULL, random_id VARCHAR(255) NOT NULL, redirect_uris TEXT NOT NULL, secret VARCHAR(255) NOT NULL, allowed_grant_types TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, allowed_scopes JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN oauth_client.redirect_uris IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN oauth_client.allowed_grant_types IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN oauth_client.allowed_scopes IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE TABLE auth_code (id UUID NOT NULL, client_id VARCHAR(80) NOT NULL, token VARCHAR(255) NOT NULL, redirect_uri TEXT NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5933D02C5F37A13B ON auth_code (token)');
        $this->addSql('CREATE INDEX IDX_5933D02C19EB6921 ON auth_code (client_id)');
        $this->addSql('COMMENT ON COLUMN auth_code.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C3D5814AC FOREIGN KEY (commit_id) REFERENCES asset_commit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD6819EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F219519EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE auth_code ADD CONSTRAINT FK_5933D02C19EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C3D5814AC');
        $this->addSql('ALTER TABLE access_token DROP CONSTRAINT FK_B6A2DD6819EB6921');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F219519EB6921');
        $this->addSql('ALTER TABLE auth_code DROP CONSTRAINT FK_5933D02C19EB6921');
        $this->addSql('DROP TABLE auth_code');
        $this->addSql('DROP TABLE oauth_client');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE form_schema');
        $this->addSql('DROP TABLE failed_event');
        $this->addSql('DROP TABLE asset_commit');
        $this->addSql('DROP TABLE asset');
        $this->addSql('DROP TABLE bulk_data');
    }
}
