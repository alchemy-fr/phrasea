<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250526161602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE entity_type (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C412EE0282D40A1F ON entity_type (workspace_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_ws_type ON entity_type (workspace_id, name)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN entity_type.id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN entity_type.workspace_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN entity_type.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN entity_type.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE entity_type ADD CONSTRAINT FK_C412EE0282D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ADD entity_type_id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_definition.entity_type_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ADD CONSTRAINT FK_6C5628BD5681BEB0 FOREIGN KEY (entity_type_id) REFERENCES entity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6C5628BD5681BEB0 ON attribute_definition (entity_type_id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX attr_entity_type_idx
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity ADD type_id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_entity.type_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity ADD CONSTRAINT FK_2CC96A32C54C8C93 FOREIGN KEY (type_id) REFERENCES entity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX entity_type_idx ON attribute_entity (type_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition DROP CONSTRAINT FK_6C5628BD5681BEB0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity DROP CONSTRAINT FK_2CC96A32C54C8C93
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE entity_type DROP CONSTRAINT FK_C412EE0282D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE entity_type
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_6C5628BD5681BEB0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition DROP entity_type_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX entity_type_idx
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity DROP type_id
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX attr_entity_type_idx ON attribute_entity (type)
        SQL);
    }
}
