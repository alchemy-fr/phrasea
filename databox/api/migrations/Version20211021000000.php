<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Day-0 migration: the schema as it was before the first recorded migration (Version20211021162739).
 *
 * The first migration was an empty marker: the initial schema had been created with
 * `doctrine:schema:update` and never captured, so the migrations could not be replayed
 * on an empty database (bin/dev/test-migrations.sh). This is the output of
 * `doctrine:schema:create --dump-sql` on the mapping at commit 4549afed7 (2021-10-20,
 * the last surviving commit of the PS-355 branch before the first migration), completed
 * with what that branch changed through `doctrine:schema:update` before its next
 * migration (Version20211104154911) and that the squash merge (1db21a008) lost:
 * metadata_definition/metadata_value became attribute_definition/attribute, the
 * sub_definition_class, sub_definition_rule, sdr_includes and sdr_excludes tables and
 * sub_definition_spec.class_id were added, and the sub_definition_spec (workspace_id, name)
 * index became sds_ws_name.
 *
 * The `(DC2Type:uuid)` column comments come from the same branch: it pinned
 * ramsey/uuid-doctrine below 1.7, whose uuid type requires the comment hint.
 *
 * On an existing database (setup.sh already ran `doctrine:schema:update` and marked
 * every version as executed, or the app was upgraded through migrate.sh) the tables
 * already exist: this migration is then recorded without running anything.
 */
final class Version20211021000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Day-0: base schema preceding the first migration';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->createSchemaManager()->tablesExist(['workspace'])) {
            $this->warnIf(true, 'Base schema already present, nothing to do.');

            return;
        }

        $this->addSql('CREATE TABLE basket (id UUID NOT NULL, collection_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2246507B514956FD ON basket (collection_id)');
        $this->addSql('CREATE TABLE media_index (id UUID NOT NULL, collection_id UUID NOT NULL, workspace_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9F6AA3B0514956FD ON media_index (collection_id)');
        $this->addSql('CREATE INDEX IDX_9F6AA3B082D40A1F ON media_index (workspace_id)');
        $this->addSql('CREATE TABLE sub_definition_spec (id UUID NOT NULL, workspace_id UUID NOT NULL, class_id UUID DEFAULT NULL, name VARCHAR(80) NOT NULL, use_as_preview BOOLEAN NOT NULL, use_as_thumbnail BOOLEAN NOT NULL, use_as_thumbnail_active BOOLEAN NOT NULL, definition TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4F0B98C582D40A1F ON sub_definition_spec (workspace_id)');
        $this->addSql('CREATE INDEX sds_ws_name ON sub_definition_spec (workspace_id, name)');
        $this->addSql('CREATE TABLE tag (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, locale VARCHAR(2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_389B78382D40A1F ON tag (workspace_id)');
        $this->addSql('CREATE UNIQUE INDEX ws_name_uniq ON tag (workspace_id, name)');
        $this->addSql('CREATE TABLE asset (id UUID NOT NULL, story_collection_id UUID DEFAULT NULL, reference_collection_id UUID DEFAULT NULL, file_id UUID DEFAULT NULL, workspace_id UUID NOT NULL, title VARCHAR(255) DEFAULT NULL, owner_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, locale VARCHAR(2) NOT NULL, privacy SMALLINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2AF5A5C717DFDC8 ON asset (story_collection_id)');
        $this->addSql('CREATE INDEX IDX_2AF5A5C72B16381 ON asset (reference_collection_id)');
        $this->addSql('CREATE INDEX IDX_2AF5A5C93CB796C ON asset (file_id)');
        $this->addSql('CREATE INDEX IDX_2AF5A5C82D40A1F ON asset (workspace_id)');
        $this->addSql('CREATE TABLE asset_tag (asset_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(asset_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_6983740F5DA1941 ON asset_tag (asset_id)');
        $this->addSql('CREATE INDEX IDX_6983740FBAD26311 ON asset_tag (tag_id)');
        $this->addSql('CREATE TABLE workspace (id UUID NOT NULL, name VARCHAR(255) DEFAULT NULL, owner_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE sub_definition (id UUID NOT NULL, specification_id UUID NOT NULL, asset_id UUID NOT NULL, file_id UUID NOT NULL, ready BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_44FBBC15908E2FFE ON sub_definition (specification_id)');
        $this->addSql('CREATE INDEX IDX_44FBBC155DA1941 ON sub_definition (asset_id)');
        $this->addSql('CREATE INDEX IDX_44FBBC1593CB796C ON sub_definition (file_id)');
        $this->addSql('CREATE TABLE collection (id UUID NOT NULL, parent_id UUID DEFAULT NULL, workspace_id UUID NOT NULL, title VARCHAR(255) DEFAULT NULL, owner_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, locale VARCHAR(2) NOT NULL, privacy SMALLINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FC4D6532727ACA70 ON collection (parent_id)');
        $this->addSql('CREATE INDEX IDX_FC4D653282D40A1F ON collection (workspace_id)');
        $this->addSql('CREATE TABLE tag_filter_rule (id UUID NOT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, object_type SMALLINT NOT NULL, object_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX tfr_user_idx ON tag_filter_rule (user_type, user_id)');
        $this->addSql('CREATE INDEX tfr_object_idx ON tag_filter_rule (object_type, object_id)');
        $this->addSql('CREATE INDEX tfr_user_type_idx ON tag_filter_rule (user_type)');
        $this->addSql('CREATE UNIQUE INDEX tfr_uniq_ace ON tag_filter_rule (user_type, user_id, object_type, object_id)');
        $this->addSql('CREATE TABLE tfr_includes (tag_filter_rule_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(tag_filter_rule_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_BF991C3B5D544FE0 ON tfr_includes (tag_filter_rule_id)');
        $this->addSql('CREATE INDEX IDX_BF991C3BBAD26311 ON tfr_includes (tag_id)');
        $this->addSql('CREATE TABLE tfr_excludes (tag_filter_rule_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(tag_filter_rule_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_EC38A0805D544FE0 ON tfr_excludes (tag_filter_rule_id)');
        $this->addSql('CREATE INDEX IDX_EC38A080BAD26311 ON tfr_excludes (tag_id)');
        $this->addSql('CREATE TABLE file (id UUID NOT NULL, workspace_id UUID NOT NULL, type VARCHAR(100) DEFAULT NULL, size INT DEFAULT NULL, checksum VARCHAR(64) DEFAULT NULL, path VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8C9F361082D40A1F ON file (workspace_id)');
        $this->addSql('CREATE TABLE collection_asset (id UUID NOT NULL, collection_id UUID NOT NULL, asset_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4557E20F514956FD ON collection_asset (collection_id)');
        $this->addSql('CREATE INDEX IDX_4557E20F5DA1941 ON collection_asset (asset_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_coll_asset ON collection_asset (collection_id, asset_id)');
        $this->addSql('CREATE TABLE failed_event (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(150) NOT NULL, payload JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE access_control_entry (id UUID NOT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, object_type VARCHAR(20) NOT NULL, object_id UUID DEFAULT NULL, mask INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX user_idx ON access_control_entry (user_type, user_id)');
        $this->addSql('CREATE INDEX object_idx ON access_control_entry (object_type, object_id)');
        $this->addSql('CREATE INDEX user_type_idx ON access_control_entry (user_type)');
        $this->addSql('CREATE INDEX object_type_idx ON access_control_entry (object_type)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ace ON access_control_entry (user_type, user_id, object_type, object_id)');
        $this->addSql('CREATE TABLE oauth_client (id VARCHAR(80) NOT NULL, random_id VARCHAR(255) NOT NULL, redirect_uris TEXT NOT NULL, secret VARCHAR(255) NOT NULL, allowed_grant_types TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, allowed_scopes JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN oauth_client.redirect_uris IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN oauth_client.allowed_grant_types IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN oauth_client.allowed_scopes IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE TABLE auth_code (id UUID NOT NULL, client_id VARCHAR(80) NOT NULL, token VARCHAR(255) NOT NULL, redirect_uri TEXT NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5933D02C5F37A13B ON auth_code (token)');
        $this->addSql('CREATE INDEX IDX_5933D02C19EB6921 ON auth_code (client_id)');
        $this->addSql('CREATE TABLE refresh_token (id UUID NOT NULL, client_id VARCHAR(80) NOT NULL, token VARCHAR(255) NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C74F21955F37A13B ON refresh_token (token)');
        $this->addSql('CREATE INDEX IDX_C74F219519EB6921 ON refresh_token (client_id)');
        $this->addSql('CREATE TABLE access_token (id UUID NOT NULL, client_id VARCHAR(80) NOT NULL, token VARCHAR(255) NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6A2DD685F37A13B ON access_token (token)');
        $this->addSql('CREATE INDEX IDX_B6A2DD6819EB6921 ON access_token (client_id)');
        $this->addSql('CREATE TABLE multipart_upload (id UUID NOT NULL, filename VARCHAR(255) NOT NULL, type VARCHAR(150) NOT NULL, size INT NOT NULL, upload_id VARCHAR(150) NOT NULL, path VARCHAR(255) NOT NULL, complete BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE attribute_definition (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(100) NOT NULL, file_type VARCHAR(100) DEFAULT NULL, field_type VARCHAR(50) NOT NULL, editable BOOLEAN NOT NULL, fallbacks TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6C5628BD82D40A1F ON attribute_definition (workspace_id)');
        $this->addSql('COMMENT ON COLUMN attribute_definition.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_definition.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_definition.fallbacks IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE attribute (id UUID NOT NULL, asset_id UUID NOT NULL, definition_id UUID NOT NULL, translation_id UUID DEFAULT NULL, locale VARCHAR(2) NOT NULL, value TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FA7AEFFB5DA1941 ON attribute (asset_id)');
        $this->addSql('CREATE INDEX IDX_FA7AEFFBD11EA911 ON attribute (definition_id)');
        $this->addSql('CREATE INDEX IDX_FA7AEFFB9CAA2B25 ON attribute (translation_id)');
        $this->addSql('COMMENT ON COLUMN attribute.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute.definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute.translation_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE sub_definition_class (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C7AB378482D40A1F ON sub_definition_class (workspace_id)');
        $this->addSql('CREATE INDEX sdc_ws_name ON sub_definition_class (workspace_id, name)');
        $this->addSql('COMMENT ON COLUMN sub_definition_class.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_definition_class.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE sub_definition_rule (id UUID NOT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, object_type SMALLINT NOT NULL, object_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX sdr_user_idx ON sub_definition_rule (user_type, user_id)');
        $this->addSql('CREATE INDEX sdr_object_idx ON sub_definition_rule (object_type, object_id)');
        $this->addSql('CREATE INDEX sdr_user_type_idx ON sub_definition_rule (user_type)');
        $this->addSql('CREATE UNIQUE INDEX sdr_uniq_rule ON sub_definition_rule (user_type, user_id, object_type, object_id)');
        $this->addSql('COMMENT ON COLUMN sub_definition_rule.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_definition_rule.object_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX IDX_4F0B98C5EA000B10 ON sub_definition_spec (class_id)');
        $this->addSql('COMMENT ON COLUMN sub_definition_spec.class_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE sdr_includes (sub_definition_rule_id UUID NOT NULL, sub_definition_class_id UUID NOT NULL, PRIMARY KEY(sub_definition_rule_id, sub_definition_class_id))');
        $this->addSql('CREATE INDEX IDX_936D4C751AEEFE4 ON sdr_includes (sub_definition_rule_id)');
        $this->addSql('CREATE INDEX IDX_936D4C75517EACFF ON sdr_includes (sub_definition_class_id)');
        $this->addSql('COMMENT ON COLUMN sdr_includes.sub_definition_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sdr_includes.sub_definition_class_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE sdr_excludes (sub_definition_rule_id UUID NOT NULL, sub_definition_class_id UUID NOT NULL, PRIMARY KEY(sub_definition_rule_id, sub_definition_class_id))');
        $this->addSql('CREATE INDEX IDX_C0CCF0CE1AEEFE4 ON sdr_excludes (sub_definition_rule_id)');
        $this->addSql('CREATE INDEX IDX_C0CCF0CE517EACFF ON sdr_excludes (sub_definition_class_id)');
        $this->addSql('COMMENT ON COLUMN sdr_excludes.sub_definition_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sdr_excludes.sub_definition_class_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN basket.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN basket.collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN media_index.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN media_index.collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN media_index.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_definition_spec.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_definition_spec.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tag.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tag.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset.story_collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset.reference_collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset.file_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_tag.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_tag.tag_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN workspace.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_definition.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_definition.specification_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_definition.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_definition.file_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN collection.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN collection.parent_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN collection.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.object_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tfr_includes.tag_filter_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tfr_includes.tag_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tfr_excludes.tag_filter_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tfr_excludes.tag_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN file.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN file.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN collection_asset.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN collection_asset.collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN collection_asset.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN failed_event.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN access_control_entry.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN access_control_entry.object_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN auth_code.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN refresh_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN access_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN multipart_upload.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE basket ADD CONSTRAINT FK_2246507B514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE media_index ADD CONSTRAINT FK_9F6AA3B0514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE media_index ADD CONSTRAINT FK_9F6AA3B082D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition_spec ADD CONSTRAINT FK_4F0B98C582D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B78382D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C717DFDC8 FOREIGN KEY (story_collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C72B16381 FOREIGN KEY (reference_collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C93CB796C FOREIGN KEY (file_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_tag ADD CONSTRAINT FK_6983740F5DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_tag ADD CONSTRAINT FK_6983740FBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition ADD CONSTRAINT FK_44FBBC15908E2FFE FOREIGN KEY (specification_id) REFERENCES sub_definition_spec (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition ADD CONSTRAINT FK_44FBBC155DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition ADD CONSTRAINT FK_44FBBC1593CB796C FOREIGN KEY (file_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE collection ADD CONSTRAINT FK_FC4D6532727ACA70 FOREIGN KEY (parent_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE collection ADD CONSTRAINT FK_FC4D653282D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tfr_includes ADD CONSTRAINT FK_BF991C3B5D544FE0 FOREIGN KEY (tag_filter_rule_id) REFERENCES tag_filter_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tfr_includes ADD CONSTRAINT FK_BF991C3BBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tfr_excludes ADD CONSTRAINT FK_EC38A0805D544FE0 FOREIGN KEY (tag_filter_rule_id) REFERENCES tag_filter_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tfr_excludes ADD CONSTRAINT FK_EC38A080BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F361082D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE collection_asset ADD CONSTRAINT FK_4557E20F514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE collection_asset ADD CONSTRAINT FK_4557E20F5DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE auth_code ADD CONSTRAINT FK_5933D02C19EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F219519EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD6819EB6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute_definition ADD CONSTRAINT FK_6C5628BD82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFB5DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFBD11EA911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFB9CAA2B25 FOREIGN KEY (translation_id) REFERENCES attribute (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition_class ADD CONSTRAINT FK_C7AB378482D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition_spec ADD CONSTRAINT FK_4F0B98C5EA000B10 FOREIGN KEY (class_id) REFERENCES sub_definition_class (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sdr_includes ADD CONSTRAINT FK_936D4C751AEEFE4 FOREIGN KEY (sub_definition_rule_id) REFERENCES sub_definition_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sdr_includes ADD CONSTRAINT FK_936D4C75517EACFF FOREIGN KEY (sub_definition_class_id) REFERENCES sub_definition_class (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sdr_excludes ADD CONSTRAINT FK_C0CCF0CE1AEEFE4 FOREIGN KEY (sub_definition_rule_id) REFERENCES sub_definition_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sdr_excludes ADD CONSTRAINT FK_C0CCF0CE517EACFF FOREIGN KEY (sub_definition_class_id) REFERENCES sub_definition_class (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE basket DROP CONSTRAINT FK_2246507B514956FD');
        $this->addSql('ALTER TABLE media_index DROP CONSTRAINT FK_9F6AA3B0514956FD');
        $this->addSql('ALTER TABLE media_index DROP CONSTRAINT FK_9F6AA3B082D40A1F');
        $this->addSql('ALTER TABLE sub_definition_spec DROP CONSTRAINT FK_4F0B98C582D40A1F');
        $this->addSql('ALTER TABLE tag DROP CONSTRAINT FK_389B78382D40A1F');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C717DFDC8');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C72B16381');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C93CB796C');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C82D40A1F');
        $this->addSql('ALTER TABLE asset_tag DROP CONSTRAINT FK_6983740F5DA1941');
        $this->addSql('ALTER TABLE asset_tag DROP CONSTRAINT FK_6983740FBAD26311');
        $this->addSql('ALTER TABLE sub_definition DROP CONSTRAINT FK_44FBBC15908E2FFE');
        $this->addSql('ALTER TABLE sub_definition DROP CONSTRAINT FK_44FBBC155DA1941');
        $this->addSql('ALTER TABLE sub_definition DROP CONSTRAINT FK_44FBBC1593CB796C');
        $this->addSql('ALTER TABLE collection DROP CONSTRAINT FK_FC4D6532727ACA70');
        $this->addSql('ALTER TABLE collection DROP CONSTRAINT FK_FC4D653282D40A1F');
        $this->addSql('ALTER TABLE tfr_includes DROP CONSTRAINT FK_BF991C3B5D544FE0');
        $this->addSql('ALTER TABLE tfr_includes DROP CONSTRAINT FK_BF991C3BBAD26311');
        $this->addSql('ALTER TABLE tfr_excludes DROP CONSTRAINT FK_EC38A0805D544FE0');
        $this->addSql('ALTER TABLE tfr_excludes DROP CONSTRAINT FK_EC38A080BAD26311');
        $this->addSql('ALTER TABLE file DROP CONSTRAINT FK_8C9F361082D40A1F');
        $this->addSql('ALTER TABLE collection_asset DROP CONSTRAINT FK_4557E20F514956FD');
        $this->addSql('ALTER TABLE collection_asset DROP CONSTRAINT FK_4557E20F5DA1941');
        $this->addSql('ALTER TABLE auth_code DROP CONSTRAINT FK_5933D02C19EB6921');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F219519EB6921');
        $this->addSql('ALTER TABLE access_token DROP CONSTRAINT FK_B6A2DD6819EB6921');
        $this->addSql('ALTER TABLE attribute_definition DROP CONSTRAINT FK_6C5628BD82D40A1F');
        $this->addSql('ALTER TABLE attribute DROP CONSTRAINT FK_FA7AEFFB5DA1941');
        $this->addSql('ALTER TABLE attribute DROP CONSTRAINT FK_FA7AEFFBD11EA911');
        $this->addSql('ALTER TABLE attribute DROP CONSTRAINT FK_FA7AEFFB9CAA2B25');
        $this->addSql('ALTER TABLE sub_definition_class DROP CONSTRAINT FK_C7AB378482D40A1F');
        $this->addSql('ALTER TABLE sub_definition_spec DROP CONSTRAINT FK_4F0B98C5EA000B10');
        $this->addSql('ALTER TABLE sdr_includes DROP CONSTRAINT FK_936D4C751AEEFE4');
        $this->addSql('ALTER TABLE sdr_includes DROP CONSTRAINT FK_936D4C75517EACFF');
        $this->addSql('ALTER TABLE sdr_excludes DROP CONSTRAINT FK_C0CCF0CE1AEEFE4');
        $this->addSql('ALTER TABLE sdr_excludes DROP CONSTRAINT FK_C0CCF0CE517EACFF');
        $this->addSql('DROP TABLE sdr_excludes');
        $this->addSql('DROP TABLE sdr_includes');
        $this->addSql('DROP TABLE sub_definition_rule');
        $this->addSql('DROP TABLE sub_definition_class');
        $this->addSql('DROP TABLE attribute');
        $this->addSql('DROP TABLE attribute_definition');
        $this->addSql('DROP TABLE multipart_upload');
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE auth_code');
        $this->addSql('DROP TABLE oauth_client');
        $this->addSql('DROP TABLE access_control_entry');
        $this->addSql('DROP TABLE failed_event');
        $this->addSql('DROP TABLE collection_asset');
        $this->addSql('DROP TABLE file');
        $this->addSql('DROP TABLE tfr_excludes');
        $this->addSql('DROP TABLE tfr_includes');
        $this->addSql('DROP TABLE tag_filter_rule');
        $this->addSql('DROP TABLE collection');
        $this->addSql('DROP TABLE sub_definition');
        $this->addSql('DROP TABLE workspace');
        $this->addSql('DROP TABLE asset_tag');
        $this->addSql('DROP TABLE asset');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE sub_definition_spec');
        $this->addSql('DROP TABLE media_index');
        $this->addSql('DROP TABLE basket');
    }
}
