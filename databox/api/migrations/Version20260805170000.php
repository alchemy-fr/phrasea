<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace tag_filter_rule (tag include/exclude lists) with attribute_filter_rule (AQL condition + multiple user/group targets)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE attribute_filter_rule (id UUID NOT NULL, workspace_id UUID NOT NULL, condition TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN attribute_filter_rule.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_filter_rule.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_filter_rule.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN attribute_filter_rule.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_133E386582D40A1F ON attribute_filter_rule (workspace_id)');
        $this->addSql('ALTER TABLE attribute_filter_rule ADD CONSTRAINT FK_133E386582D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE attribute_filter_rule_target (id UUID NOT NULL, rule_id UUID NOT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN attribute_filter_rule_target.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_filter_rule_target.rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX afrt_user_idx ON attribute_filter_rule_target (user_type, user_id)');
        $this->addSql('CREATE UNIQUE INDEX afrt_uniq_target ON attribute_filter_rule_target (rule_id, user_type, user_id)');
        $this->addSql('CREATE INDEX IDX_D246D06B744E0351 ON attribute_filter_rule_target (rule_id)');
        $this->addSql('ALTER TABLE attribute_filter_rule_target ADD CONSTRAINT FK_D246D06B744E0351 FOREIGN KEY (rule_id) REFERENCES attribute_filter_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Convert existing tag filter rules into AQL conditions:
        // include tags [a, b] => @tag = "a" AND @tag = "b" (asset must have ALL included tags)
        // exclude tags [c, d] => @tag NOT IN ("c", "d")    (any excluded tag hides the asset)
        // Rules with no tag at all are skipped (they had no effect).
        $this->addSql(<<<'SQL'
            INSERT INTO attribute_filter_rule (id, workspace_id, condition, created_at, updated_at)
            SELECT r.id, r.workspace_id,
                CONCAT_WS(' AND ',
                    (SELECT string_agg('@tag = "' || i.tag_id::text || '"', ' AND ' ORDER BY i.tag_id) FROM tfr_includes i WHERE i.tag_filter_rule_id = r.id),
                    (SELECT '@tag NOT IN ("' || string_agg(e.tag_id::text, '", "' ORDER BY e.tag_id) || '")' FROM tfr_excludes e WHERE e.tag_filter_rule_id = r.id)
                ),
                r.created_at, r.updated_at
            FROM tag_filter_rule r
            WHERE EXISTS (SELECT 1 FROM tfr_includes i WHERE i.tag_filter_rule_id = r.id)
               OR EXISTS (SELECT 1 FROM tfr_excludes e WHERE e.tag_filter_rule_id = r.id)
            SQL
        );

        // Each legacy rule had a single principal (user or group); a NULL user_id meant
        // "everyone", which is now expressed by a rule without any target.
        $this->addSql(<<<'SQL'
            INSERT INTO attribute_filter_rule_target (id, rule_id, user_type, user_id)
            SELECT md5(r.id::text || 'target')::uuid, r.id, r.user_type, r.user_id
            FROM tag_filter_rule r
            WHERE r.user_id IS NOT NULL
              AND EXISTS (SELECT 1 FROM attribute_filter_rule a WHERE a.id = r.id)
            SQL
        );

        $this->addSql('DROP TABLE tfr_includes');
        $this->addSql('DROP TABLE tfr_excludes');
        $this->addSql('DROP TABLE tag_filter_rule');
    }

    public function down(Schema $schema): void
    {
        // Schema-only: converted AQL conditions cannot be turned back into tag lists.
        $this->addSql('CREATE TABLE tag_filter_rule (id UUID NOT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, workspace_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tag_filter_rule.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX tfr_user_idx ON tag_filter_rule (user_type, user_id)');
        $this->addSql('CREATE INDEX tfr_user_type_idx ON tag_filter_rule (user_type)');
        $this->addSql('CREATE UNIQUE INDEX tfr_uniq_ace ON tag_filter_rule (user_type, user_id, workspace_id)');
        $this->addSql('CREATE INDEX IDX_322BB15582D40A1F ON tag_filter_rule (workspace_id)');
        $this->addSql('ALTER TABLE tag_filter_rule ADD CONSTRAINT FK_322BB15582D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE tfr_includes (tag_filter_rule_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(tag_filter_rule_id, tag_id))');
        $this->addSql('COMMENT ON COLUMN tfr_includes.tag_filter_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tfr_includes.tag_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX IDX_BF991C3B5D544FE0 ON tfr_includes (tag_filter_rule_id)');
        $this->addSql('CREATE INDEX IDX_BF991C3BBAD26311 ON tfr_includes (tag_id)');
        $this->addSql('ALTER TABLE tfr_includes ADD CONSTRAINT FK_BF991C3B5D544FE0 FOREIGN KEY (tag_filter_rule_id) REFERENCES tag_filter_rule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tfr_includes ADD CONSTRAINT FK_BF991C3BBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE tfr_excludes (tag_filter_rule_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(tag_filter_rule_id, tag_id))');
        $this->addSql('COMMENT ON COLUMN tfr_excludes.tag_filter_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tfr_excludes.tag_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX IDX_EC38A0805D544FE0 ON tfr_excludes (tag_filter_rule_id)');
        $this->addSql('CREATE INDEX IDX_EC38A080BAD26311 ON tfr_excludes (tag_id)');
        $this->addSql('ALTER TABLE tfr_excludes ADD CONSTRAINT FK_EC38A0805D544FE0 FOREIGN KEY (tag_filter_rule_id) REFERENCES tag_filter_rule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tfr_excludes ADD CONSTRAINT FK_EC38A080BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('DROP TABLE attribute_filter_rule_target');
        $this->addSql('DROP TABLE attribute_filter_rule');
    }
}
