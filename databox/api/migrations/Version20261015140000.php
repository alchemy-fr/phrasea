<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;

final class Version20261015140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add entity types and root hierarchy to attribute entities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX list_value_uniq');
        $this->addSql('CREATE TABLE entity_type (id UUID NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY(id))');
        $this->addSql("COMMENT ON COLUMN entity_type.id IS '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE attribute_entity ADD entity_type_id UUID DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN attribute_entity.entity_type_id IS '(DC2Type:uuid)'");
        foreach ($this->connection->iterateAssociative(<<<'SQL'
            SELECT ae.id, el.name
            FROM attribute_entity ae
            INNER JOIN entity_list el ON el.id = ae.list_id
            SQL) as $row) {
            $entityTypeId = Uuid::uuid4()->toString();
            $this->addSql(
                'INSERT INTO entity_type (id, name) VALUES (CAST(:id AS UUID), :name)',
                [
                    'id' => $entityTypeId,
                    'name' => $row['name'],
                ],
            );
            $this->addSql(
                'UPDATE attribute_entity SET entity_type_id = CAST(:entityTypeId AS UUID) WHERE id = CAST(:attributeEntityId AS UUID)',
                [
                    'entityTypeId' => $entityTypeId,
                    'attributeEntityId' => $row['id'],
                ],
            );
        }
        foreach ($this->connection->iterateAssociative('SELECT id, workspace_id, name FROM entity_list') as $row) {
            $rootId = Uuid::uuid4()->toString();
            $entityTypeId = Uuid::uuid4()->toString();
            $this->addSql(
                'INSERT INTO entity_type (id, name) VALUES (CAST(:id AS UUID), :name)',
                [
                    'id' => $entityTypeId,
                    'name' => $row['name'],
                ],
            );
            $this->addSql(<<<'SQL'
                INSERT INTO attribute_entity (id, workspace_id, list_id, value, position, status, created_at, updated_at, entity_type_id)
                VALUES (
                    CAST(:id AS UUID),
                    CAST(:workspaceId AS UUID),
                    CAST(:listId AS UUID),
                    :name,
                    0,
                    0,
                    NOW(),
                    NOW(),
                    CAST(:entityTypeId AS UUID)
                )
                SQL, [
                'id' => $rootId,
                'workspaceId' => $row['workspace_id'],
                'listId' => $row['id'],
                'name' => $row['name'],
                'entityTypeId' => $entityTypeId,
            ]);
            $this->addSql(
                'UPDATE attribute_entity SET parent_id = CAST(:rootId AS UUID) WHERE list_id = CAST(:listId AS UUID) AND id <> CAST(:rootId AS UUID)',
                [
                    'rootId' => $rootId,
                    'listId' => $row['id'],
                ],
            );
        }
        $this->addSql('ALTER TABLE attribute_entity ALTER entity_type_id SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_attribute_entity_entity_type ON attribute_entity (entity_type_id)');
        $this->addSql('ALTER TABLE attribute_entity ADD CONSTRAINT fk_attribute_entity_entity_type FOREIGN KEY (entity_type_id) REFERENCES entity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX list_value_parent_uniq ON attribute_entity (list_id, parent_id, value) WHERE parent_id IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX list_value_root_uniq ON attribute_entity (list_id, value) WHERE parent_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX list_value_root_uniq');
        $this->addSql('DROP INDEX list_value_parent_uniq');
        $this->addSql('ALTER TABLE attribute_entity DROP CONSTRAINT fk_attribute_entity_entity_type');
        $this->addSql(<<<'SQL'
            DELETE FROM attribute_entity ae
            USING entity_list el
            WHERE ae.list_id = el.id
              AND ae.parent_id IS NULL
              AND ae.value = el.name
            SQL);
        $this->addSql('DROP INDEX uniq_attribute_entity_entity_type');
        $this->addSql('ALTER TABLE attribute_entity DROP entity_type_id');
        $this->addSql('DROP TABLE entity_type');
        $this->addSql('CREATE UNIQUE INDEX list_value_uniq ON attribute_entity (list_id, value)');
    }
}
