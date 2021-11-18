<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211105153253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sub_def_class_permission (id UUID NOT NULL, class_id UUID DEFAULT NULL, user_type SMALLINT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, allow BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B56FF4F8EA000B10 ON sub_def_class_permission (class_id)');
        $this->addSql('COMMENT ON COLUMN sub_def_class_permission.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sub_def_class_permission.class_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE sub_def_class_permission ADD CONSTRAINT FK_B56FF4F8EA000B10 FOREIGN KEY (class_id) REFERENCES sub_definition_class (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition_spec ADD priority SMALLINT NULL');
        $this->addSql('UPDATE sub_definition_spec SET priority = 0');
        $this->addSql('ALTER TABLE sub_definition_spec ALTER COLUMN priority SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE sub_def_class_permission');
        $this->addSql('ALTER TABLE sub_definition_spec DROP priority');
    }
}
