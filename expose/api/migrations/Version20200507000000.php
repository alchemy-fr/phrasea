<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Day-0 migration: the schema as it was before the first recorded migration (Version20200507104602).
 *
 * The first migration was an empty marker: the initial schema had been created with
 * `doctrine:schema:update` and never captured, so the migrations could not be replayed
 * on an empty database (bin/dev/test-migrations.sh). This is the output of
 * `doctrine:schema:create --dump-sql` on the mapping at commit f29806c53 (2020-05-06).
 *
 * On an existing database (setup.sh already ran `doctrine:schema:update` and marked
 * every version as executed, or the app was upgraded through migrate.sh) the tables
 * already exist: this migration is then recorded without running anything.
 */
final class Version20200507000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Day-0: base schema preceding the first migration';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->createSchemaManager()->tablesExist(['publication'])) {
            $this->warnIf(true, 'Base schema already present, nothing to do.');

            return;
        }

        $this->addSql('CREATE TABLE asset (id UUID NOT NULL, preview_definition_id UUID DEFAULT NULL, thumbnail_definition_id UUID DEFAULT NULL, asset_id VARCHAR(255) DEFAULT NULL, path VARCHAR(255) NOT NULL, size INT NOT NULL, title VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, owner_id VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2AF5A5C9D346BBD ON asset (preview_definition_id)');
        $this->addSql('CREATE INDEX IDX_2AF5A5CEA11AF98 ON asset (thumbnail_definition_id)');
        $this->addSql('COMMENT ON COLUMN asset.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset.preview_definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset.thumbnail_definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE publication (id UUID NOT NULL, cover_id UUID DEFAULT NULL, package_id UUID DEFAULT NULL, parent_id UUID DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, enabled BOOLEAN NOT NULL, owner_id VARCHAR(255) DEFAULT NULL, publicly_listed BOOLEAN NOT NULL, slug VARCHAR(100) DEFAULT NULL, layout VARCHAR(20) NOT NULL, theme VARCHAR(30) DEFAULT NULL, begins_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, security_method VARCHAR(20) DEFAULT NULL, security_options JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AF3C6779989D9B62 ON publication (slug)');
        $this->addSql('CREATE INDEX IDX_AF3C6779922726E9 ON publication (cover_id)');
        $this->addSql('CREATE INDEX IDX_AF3C6779F44CABFF ON publication (package_id)');
        $this->addSql('CREATE INDEX IDX_AF3C6779727ACA70 ON publication (parent_id)');
        $this->addSql('COMMENT ON COLUMN publication.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN publication.cover_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN publication.package_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN publication.parent_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN publication.security_options IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE TABLE sub_definition (id UUID NOT NULL, asset_id UUID NOT NULL, name VARCHAR(30) NOT NULL, path VARCHAR(255) NOT NULL, size INT NOT NULL, mime_type VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_44FBBC155DA1941 ON sub_definition (asset_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_asset_type ON sub_definition (asset_id, name)');
        $this->addSql('COMMENT ON COLUMN sub_definition.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_definition.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE publication_asset (id UUID NOT NULL, publication_id UUID NOT NULL, asset_id UUID NOT NULL, slug VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E7711CD038B217A7 ON publication_asset (publication_id)');
        $this->addSql('CREATE INDEX IDX_E7711CD05DA1941 ON publication_asset (asset_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_url ON publication_asset (publication_id, slug)');
        $this->addSql('COMMENT ON COLUMN publication_asset.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN publication_asset.publication_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN publication_asset.asset_id IS \'(DC2Type:uuid)\'');
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
        $this->addSql('CREATE TABLE access_control_entry (id UUID NOT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, object_type VARCHAR(20) NOT NULL, object_id VARCHAR(36) DEFAULT NULL, mask INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_ace ON access_control_entry (user_type, user_id, object_type, object_id)');
        $this->addSql('COMMENT ON COLUMN access_control_entry.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C9D346BBD FOREIGN KEY (preview_definition_id) REFERENCES sub_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5CEA11AF98 FOREIGN KEY (thumbnail_definition_id) REFERENCES sub_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779922726E9 FOREIGN KEY (cover_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779F44CABFF FOREIGN KEY (package_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779727ACA70 FOREIGN KEY (parent_id) REFERENCES publication (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition ADD CONSTRAINT FK_44FBBC155DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE publication_asset ADD CONSTRAINT FK_E7711CD038B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE publication_asset ADD CONSTRAINT FK_E7711CD05DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD6819EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F219519EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE auth_code ADD CONSTRAINT FK_5933D02C19EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C9D346BBD');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5CEA11AF98');
        $this->addSql('ALTER TABLE publication DROP CONSTRAINT FK_AF3C6779922726E9');
        $this->addSql('ALTER TABLE publication DROP CONSTRAINT FK_AF3C6779F44CABFF');
        $this->addSql('ALTER TABLE publication DROP CONSTRAINT FK_AF3C6779727ACA70');
        $this->addSql('ALTER TABLE sub_definition DROP CONSTRAINT FK_44FBBC155DA1941');
        $this->addSql('ALTER TABLE publication_asset DROP CONSTRAINT FK_E7711CD038B217A7');
        $this->addSql('ALTER TABLE publication_asset DROP CONSTRAINT FK_E7711CD05DA1941');
        $this->addSql('ALTER TABLE access_token DROP CONSTRAINT FK_B6A2DD6819EB6921');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F219519EB6921');
        $this->addSql('ALTER TABLE auth_code DROP CONSTRAINT FK_5933D02C19EB6921');
        $this->addSql('DROP TABLE access_control_entry');
        $this->addSql('DROP TABLE auth_code');
        $this->addSql('DROP TABLE oauth_client');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE publication_asset');
        $this->addSql('DROP TABLE sub_definition');
        $this->addSql('DROP TABLE publication');
        $this->addSql('DROP TABLE asset');
    }
}
