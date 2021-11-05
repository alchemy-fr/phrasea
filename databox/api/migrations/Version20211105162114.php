<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211105162114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sdr_allowed (sub_definition_rule_id UUID NOT NULL, sub_definition_class_id UUID NOT NULL, PRIMARY KEY(sub_definition_rule_id, sub_definition_class_id))');
        $this->addSql('CREATE INDEX IDX_E8839A321AEEFE4 ON sdr_allowed (sub_definition_rule_id)');
        $this->addSql('CREATE INDEX IDX_E8839A32517EACFF ON sdr_allowed (sub_definition_class_id)');
        $this->addSql('COMMENT ON COLUMN sdr_allowed.sub_definition_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sdr_allowed.sub_definition_class_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE sdr_allowed ADD CONSTRAINT FK_E8839A321AEEFE4 FOREIGN KEY (sub_definition_rule_id) REFERENCES sub_definition_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sdr_allowed ADD CONSTRAINT FK_E8839A32517EACFF FOREIGN KEY (sub_definition_class_id) REFERENCES sub_definition_class (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE sdr_includes');
        $this->addSql('DROP TABLE sdr_excludes');
        $this->addSql('DROP TABLE sub_def_class_permission');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE sdr_includes (sub_definition_rule_id UUID NOT NULL, sub_definition_class_id UUID NOT NULL, PRIMARY KEY(sub_definition_rule_id, sub_definition_class_id))');
        $this->addSql('CREATE INDEX idx_936d4c75517eacff ON sdr_includes (sub_definition_class_id)');
        $this->addSql('CREATE INDEX idx_936d4c751aeefe4 ON sdr_includes (sub_definition_rule_id)');
        $this->addSql('COMMENT ON COLUMN sdr_includes.sub_definition_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sdr_includes.sub_definition_class_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE sdr_excludes (sub_definition_rule_id UUID NOT NULL, sub_definition_class_id UUID NOT NULL, PRIMARY KEY(sub_definition_rule_id, sub_definition_class_id))');
        $this->addSql('CREATE INDEX idx_c0ccf0ce517eacff ON sdr_excludes (sub_definition_class_id)');
        $this->addSql('CREATE INDEX idx_c0ccf0ce1aeefe4 ON sdr_excludes (sub_definition_rule_id)');
        $this->addSql('COMMENT ON COLUMN sdr_excludes.sub_definition_rule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sdr_excludes.sub_definition_class_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE sub_def_class_permission (id UUID NOT NULL, class_id UUID DEFAULT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, allow BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_b56ff4f8ea000b10 ON sub_def_class_permission (class_id)');
        $this->addSql('COMMENT ON COLUMN sub_def_class_permission.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_def_class_permission.class_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE sdr_includes ADD CONSTRAINT fk_936d4c751aeefe4 FOREIGN KEY (sub_definition_rule_id) REFERENCES sub_definition_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sdr_includes ADD CONSTRAINT fk_936d4c75517eacff FOREIGN KEY (sub_definition_class_id) REFERENCES sub_definition_class (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sdr_excludes ADD CONSTRAINT fk_c0ccf0ce1aeefe4 FOREIGN KEY (sub_definition_rule_id) REFERENCES sub_definition_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sdr_excludes ADD CONSTRAINT fk_c0ccf0ce517eacff FOREIGN KEY (sub_definition_class_id) REFERENCES sub_definition_class (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_def_class_permission ADD CONSTRAINT fk_b56ff4f8ea000b10 FOREIGN KEY (class_id) REFERENCES sub_definition_class (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE sdr_allowed');
    }
}
