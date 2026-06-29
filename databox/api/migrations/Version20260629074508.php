<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629074508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add asset policy tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE asset_policy (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(255) NOT NULL, priority INT NOT NULL, conditions JSON NOT NULL, actions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id VARCHAR(36) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_18508A0B82D40A1F ON asset_policy (workspace_id)');
        $this->addSql('COMMENT ON COLUMN asset_policy.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_policy.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_policy.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE asset_policy_dependency (id UUID NOT NULL, policy_id UUID NOT NULL, object_type VARCHAR(15) NOT NULL, object_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4319D0192D29E3C6 ON asset_policy_dependency (policy_id)');
        $this->addSql('COMMENT ON COLUMN asset_policy_dependency.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_policy_dependency.policy_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_policy_dependency.object_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE asset_policy_user (id UUID NOT NULL, policy_id UUID DEFAULT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CB2577F52D29E3C6 ON asset_policy_user (policy_id)');
        $this->addSql('CREATE INDEX apu_user_idx ON asset_policy_user (user_type, user_id)');
        $this->addSql('COMMENT ON COLUMN asset_policy_user.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_policy_user.policy_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset_policy ADD CONSTRAINT FK_18508A0B82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_policy_dependency ADD CONSTRAINT FK_4319D0192D29E3C6 FOREIGN KEY (policy_id) REFERENCES asset_policy (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_policy_user ADD CONSTRAINT FK_CB2577F52D29E3C6 FOREIGN KEY (policy_id) REFERENCES asset_policy (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_policy DROP CONSTRAINT FK_18508A0B82D40A1F');
        $this->addSql('ALTER TABLE asset_policy_dependency DROP CONSTRAINT FK_4319D0192D29E3C6');
        $this->addSql('ALTER TABLE asset_policy_user DROP CONSTRAINT FK_CB2577F52D29E3C6');
        $this->addSql('DROP TABLE asset_policy');
        $this->addSql('DROP TABLE asset_policy_dependency');
        $this->addSql('DROP TABLE asset_policy_user');
    }
}
