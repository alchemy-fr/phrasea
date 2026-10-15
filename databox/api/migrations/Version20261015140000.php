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
        return 'Add one-to-one entity type to attribute entities';
    }

    public function up(Schema $schema): void
    {
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
        $this->addSql('ALTER TABLE attribute_entity ALTER entity_type_id SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_attribute_entity_entity_type ON attribute_entity (entity_type_id)');
        $this->addSql('ALTER TABLE attribute_entity ADD CONSTRAINT fk_attribute_entity_entity_type FOREIGN KEY (entity_type_id) REFERENCES entity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity DROP CONSTRAINT fk_attribute_entity_entity_type');
        $this->addSql('DROP INDEX uniq_attribute_entity_entity_type');
        $this->addSql('ALTER TABLE attribute_entity DROP entity_type_id');
        $this->addSql('DROP TABLE entity_type');
    }
}
