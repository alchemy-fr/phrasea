<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230925140946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE alternate_url ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN alternate_url.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE asset ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE asset ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE asset_data_template ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE asset_data_template ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset_data_template.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_data_template.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE asset_file_version ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset_file_version.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE asset_relationship ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE asset_relationship ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset_relationship.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_relationship.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE asset_rendition ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE asset_rendition ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset_rendition.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_rendition.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE attribute ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE attribute ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN attribute.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN attribute.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE attribute_class ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN attribute_class.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE attribute_definition ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE attribute_definition ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN attribute_definition.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN attribute_definition.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE collection ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE collection ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE collection ALTER deleted_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN collection.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN collection.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN collection.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE collection_asset ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN collection_asset.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE esindex_state ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE esindex_state ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN esindex_state.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN esindex_state.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE failed_event ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN failed_event.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE file ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE file ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN file.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN file.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE integration_data ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE integration_data ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN integration_data.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE multipart_upload ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN multipart_upload.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE populate_pass ALTER ended_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE populate_pass ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN populate_pass.ended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN populate_pass.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE rendition_class ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN rendition_class.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE rendition_definition ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE rendition_definition ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN rendition_definition.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN rendition_definition.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE rendition_rule ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE rendition_rule ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN rendition_rule.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN rendition_rule.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE tag ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE tag ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN tag.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tag.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE tag_filter_rule ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE tag_filter_rule ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE template_attribute ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE template_attribute ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN template_attribute.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN template_attribute.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_preference ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE user_preference ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN user_preference.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_preference.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE webhook ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN webhook.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE webhook_log ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN webhook_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE workspace ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace ALTER deleted_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN workspace.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN workspace.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN workspace.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE workspace_env ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace_env ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN workspace_env.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN workspace_env.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE workspace_integration ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace_integration ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN workspace_integration.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN workspace_integration.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE workspace_secret ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace_secret ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN workspace_secret.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN workspace_secret.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE workspace_integration ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace_integration ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN workspace_integration.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN workspace_integration.updated_at IS NULL');
        $this->addSql('ALTER TABLE esindex_state ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE esindex_state ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN esindex_state.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN esindex_state.updated_at IS NULL');
        $this->addSql('ALTER TABLE collection ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE collection ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE collection ALTER deleted_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN collection.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN collection.updated_at IS NULL');
        $this->addSql('COMMENT ON COLUMN collection.deleted_at IS NULL');
        $this->addSql('ALTER TABLE webhook ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN webhook.created_at IS NULL');
        $this->addSql('ALTER TABLE integration_data ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE integration_data ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN integration_data.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN integration_data.updated_at IS NULL');
        $this->addSql('ALTER TABLE tag_filter_rule ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE tag_filter_rule ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.updated_at IS NULL');
        $this->addSql('ALTER TABLE user_preference ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE user_preference ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN user_preference.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN user_preference.updated_at IS NULL');
        $this->addSql('ALTER TABLE attribute_class ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN attribute_class.created_at IS NULL');
        $this->addSql('ALTER TABLE webhook_log ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN webhook_log.created_at IS NULL');
        $this->addSql('ALTER TABLE asset_rendition ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE asset_rendition ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset_rendition.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN asset_rendition.updated_at IS NULL');
        $this->addSql('ALTER TABLE tag ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE tag ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN tag.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN tag.updated_at IS NULL');
        $this->addSql('ALTER TABLE workspace ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace ALTER deleted_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN workspace.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN workspace.updated_at IS NULL');
        $this->addSql('COMMENT ON COLUMN workspace.deleted_at IS NULL');
        $this->addSql('ALTER TABLE template_attribute ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE template_attribute ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN template_attribute.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN template_attribute.updated_at IS NULL');
        $this->addSql('ALTER TABLE workspace_secret ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace_secret ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN workspace_secret.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN workspace_secret.updated_at IS NULL');
        $this->addSql('ALTER TABLE rendition_class ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN rendition_class.created_at IS NULL');
        $this->addSql('ALTER TABLE populate_pass ALTER ended_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE populate_pass ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN populate_pass.ended_at IS NULL');
        $this->addSql('COMMENT ON COLUMN populate_pass.created_at IS NULL');
        $this->addSql('ALTER TABLE failed_event ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN failed_event.created_at IS NULL');
        $this->addSql('ALTER TABLE rendition_definition ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE rendition_definition ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN rendition_definition.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN rendition_definition.updated_at IS NULL');
        $this->addSql('ALTER TABLE rendition_rule ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE rendition_rule ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN rendition_rule.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN rendition_rule.updated_at IS NULL');
        $this->addSql('ALTER TABLE multipart_upload ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN multipart_upload.created_at IS NULL');
        $this->addSql('ALTER TABLE asset_data_template ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE asset_data_template ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset_data_template.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN asset_data_template.updated_at IS NULL');
        $this->addSql('ALTER TABLE attribute_definition ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE attribute_definition ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN attribute_definition.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN attribute_definition.updated_at IS NULL');
        $this->addSql('ALTER TABLE file ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE file ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN file.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN file.updated_at IS NULL');
        $this->addSql('ALTER TABLE attribute ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE attribute ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN attribute.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN attribute.updated_at IS NULL');
        $this->addSql('ALTER TABLE collection_asset ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN collection_asset.created_at IS NULL');
        $this->addSql('ALTER TABLE alternate_url ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN alternate_url.created_at IS NULL');
        $this->addSql('ALTER TABLE asset_relationship ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE asset_relationship ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset_relationship.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN asset_relationship.updated_at IS NULL');
        $this->addSql('ALTER TABLE workspace_env ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE workspace_env ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN workspace_env.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN workspace_env.updated_at IS NULL');
        $this->addSql('ALTER TABLE asset ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE asset ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN asset.updated_at IS NULL');
        $this->addSql('ALTER TABLE asset_file_version ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset_file_version.created_at IS NULL');
    }
}
