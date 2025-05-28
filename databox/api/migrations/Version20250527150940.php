<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250527150940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE entity_list (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C04413382D40A1F ON entity_list (workspace_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_ws_type ON entity_list (workspace_id, name)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN entity_list.id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN entity_list.workspace_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN entity_list.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN entity_list.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE entity_list ADD CONSTRAINT FK_C04413382D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ADD entity_list_id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_definition.entity_list_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ADD CONSTRAINT FK_6C5628BDAE6324A8 FOREIGN KEY (entity_list_id) REFERENCES entity_list (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6C5628BDAE6324A8 ON attribute_definition (entity_list_id)
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO entity_list (id, workspace_id, name, created_at, updated_at) SELECT DISTINCT ON (workspace_id, type) id, workspace_id, type, created_at, updated_at FROM attribute_entity
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE attribute_definition SET entity_list_id = (SELECT id FROM entity_list WHERE name = attribute_definition.entity_type AND workspace_id = attribute_definition.workspace_id)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition DROP entity_type
        SQL);

        $this->addSql(<<<'SQL'
            DROP INDEX attr_entity_type_idx
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity ADD list_id UUID DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE attribute_entity SET list_id = (SELECT id FROM entity_list WHERE name = attribute_entity.type AND workspace_id = attribute_entity.workspace_id)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity ALTER list_id SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity DROP type
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_entity.list_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity ADD CONSTRAINT FK_2CC96A323DAE168B FOREIGN KEY (list_id) REFERENCES entity_list (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX entity_list_idx ON attribute_entity (list_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition DROP CONSTRAINT FK_6C5628BDAE6324A8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity DROP CONSTRAINT FK_2CC96A323DAE168B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE entity_list DROP CONSTRAINT FK_C04413382D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE entity_list
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_6C5628BDAE6324A8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ADD entity_type VARCHAR(100) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition DROP entity_list_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX entity_list_idx
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity ADD type VARCHAR(100) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity DROP list_id
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX attr_entity_type_idx ON attribute_entity (type)
        SQL);
    }
}
