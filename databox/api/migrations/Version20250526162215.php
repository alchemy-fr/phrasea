<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250526162215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO entity_type (id, workspace_id, name, created_at, updated_at) SELECT DISTINCT ON (workspace_id, type) id, workspace_id, type, created_at, updated_at FROM attribute_entity
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE attribute_definition SET entity_type_id = (SELECT id FROM entity_type WHERE name = attribute_definition.entity_type AND workspace_id = attribute_definition.workspace_id)
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE attribute_entity SET type_id = (SELECT id FROM entity_type WHERE name = attribute_entity.type AND workspace_id = attribute_entity.workspace_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity ALTER type_id SET NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_entity ALTER type_id DROP NOT NULL
        SQL);
    }
}
