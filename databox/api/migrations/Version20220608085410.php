<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220608085410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attribute_class (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E73FA2982D40A1F ON attribute_class (workspace_id)');
        $this->addSql('CREATE UNIQUE INDEX attr_class_uniq ON attribute_class (workspace_id, name)');
        $this->addSql('COMMENT ON COLUMN attribute_class.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_class.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE attribute_class ADD CONSTRAINT FK_E73FA2982D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute_definition ADD class_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN attribute_definition.class_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE attribute_definition ADD CONSTRAINT FK_6C5628BDEA000B10 FOREIGN KEY (class_id) REFERENCES attribute_class (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6C5628BDEA000B10 ON attribute_definition (class_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE attribute_definition DROP CONSTRAINT FK_6C5628BDEA000B10');
        $this->addSql('DROP TABLE attribute_class');
        $this->addSql('DROP INDEX IDX_6C5628BDEA000B10');
        $this->addSql('ALTER TABLE attribute_definition DROP class_id');
    }
}
