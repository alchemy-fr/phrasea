<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316144506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove collection level on rendition policy and tag filter rule';
    }

    public function up(Schema $schema): void
    {
        // Move rendition rules to ACL on rendition policy
        $this->addSql('DELETE FROM rendition_rule WHERE object_type = 1');
        $this->addSql('INSERT INTO access_control_entry (id, user_type, user_id, object_type, object_id, mask, created_at) SELECT a.id, rr.user_type, rr.user_id, \'rendition_policy\' AS object_type, a.rendition_policy_id AS object_id, 513 AS permission, rr.created_at AS created_at FROM sdr_allowed a INNER JOIN rendition_rule rr ON a.rendition_rule_id = rr.id');
        $this->addSql('ALTER TABLE sdr_allowed DROP CONSTRAINT fk_e8839a3254995832');
        $this->addSql('ALTER TABLE sdr_allowed DROP CONSTRAINT fk_e8839a329f407309');
        $this->addSql('DROP TABLE sdr_allowed');
        $this->addSql('DROP TABLE rendition_rule');
        $this->addSql('DROP INDEX tfr_object_idx');
        $this->addSql('DROP INDEX tfr_uniq_ace');

        // Tag filter rule: remove collection level
        $this->addSql('DELETE FROM tag_filter_rule WHERE object_type = 1');
        $this->addSql('ALTER TABLE tag_filter_rule DROP object_type');
        $this->addSql('ALTER TABLE tag_filter_rule RENAME COLUMN object_id TO workspace_id');
        $this->addSql('ALTER TABLE tag_filter_rule ADD CONSTRAINT FK_322BB15582D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_322BB15582D40A1F ON tag_filter_rule (workspace_id)');
        $this->addSql('CREATE UNIQUE INDEX tfr_uniq_ace ON tag_filter_rule (user_type, user_id, workspace_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sdr_allowed (rendition_rule_id UUID NOT NULL, rendition_policy_id UUID NOT NULL, PRIMARY KEY(rendition_rule_id, rendition_policy_id))');
        $this->addSql('CREATE INDEX idx_e8839a3254995832 ON sdr_allowed (rendition_rule_id)');
        $this->addSql('CREATE INDEX idx_e8839a329f407309 ON sdr_allowed (rendition_policy_id)');
        $this->addSql('COMMENT ON COLUMN sdr_allowed.rendition_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sdr_allowed.rendition_policy_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE rendition_rule (id UUID NOT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, object_type SMALLINT NOT NULL, object_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX rend_uniq_rule ON rendition_rule (user_type, user_id, object_type, object_id)');
        $this->addSql('CREATE INDEX rr_object_idx ON rendition_rule (object_type, object_id)');
        $this->addSql('CREATE INDEX rr_user_idx ON rendition_rule (user_type, user_id)');
        $this->addSql('CREATE INDEX rr_user_type_idx ON rendition_rule (user_type)');
        $this->addSql('COMMENT ON COLUMN rendition_rule.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN rendition_rule.object_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN rendition_rule.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN rendition_rule.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE sdr_allowed ADD CONSTRAINT fk_e8839a3254995832 FOREIGN KEY (rendition_rule_id) REFERENCES rendition_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sdr_allowed ADD CONSTRAINT fk_e8839a329f407309 FOREIGN KEY (rendition_policy_id) REFERENCES rendition_policy (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tag_filter_rule DROP CONSTRAINT FK_322BB15582D40A1F');
        $this->addSql('DROP INDEX IDX_322BB15582D40A1F');
        $this->addSql('DROP INDEX tfr_uniq_ace');
        $this->addSql('ALTER TABLE tag_filter_rule ADD object_type SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE tag_filter_rule RENAME COLUMN workspace_id TO object_id');
        $this->addSql('CREATE INDEX tfr_object_idx ON tag_filter_rule (object_type, object_id)');
        $this->addSql('CREATE UNIQUE INDEX tfr_uniq_ace ON tag_filter_rule (user_type, user_id, object_type, object_id)');
    }
}
