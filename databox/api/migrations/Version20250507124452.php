<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250507124452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_list (id UUID NOT NULL, title VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, public BOOLEAN NOT NULL, owner_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_list.id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_list.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_list.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_list_item (id UUID NOT NULL, list_id UUID NOT NULL, definition_id UUID DEFAULT NULL, type SMALLINT NOT NULL, key VARCHAR(150) DEFAULT NULL, position INT NOT NULL, format VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E7F48A713DAE168B ON attribute_list_item (list_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E7F48A71D11EA911 ON attribute_list_item (definition_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX list_def_uniq ON attribute_list_item (list_id, definition_id, key, type)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_list_item.id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_list_item.list_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attribute_list_item.definition_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_list_item ADD CONSTRAINT FK_E7F48A713DAE168B FOREIGN KEY (list_id) REFERENCES attribute_list (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_list_item ADD CONSTRAINT FK_E7F48A71D11EA911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_list_item DROP CONSTRAINT FK_E7F48A713DAE168B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_list_item DROP CONSTRAINT FK_E7F48A71D11EA911
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE attribute_list
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE attribute_list_item
        SQL);
    }
}
