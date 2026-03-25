<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260324145622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove duplicate entries in the attribute_entity table and update the attribute table to reference the retained entry. Then create a unique index on the value column of attribute_entity to prevent future duplicates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            WITH duplicates AS (
              SELECT
                list_id,
                value,
                ARRAY_AGG(id) AS all_ids
              FROM attribute_entity
              GROUP BY list_id, value
              HAVING COUNT(*) > 1
            ),
            to_delete AS (
              SELECT
                unnest(all_ids[2:]) AS id_to_delete,  -- all except the first (retained)
                all_ids[1] AS retained_id,  -- the first ID to retain
                value,
                list_id
              FROM duplicates
            )
            UPDATE attribute a
            SET value = t.retained_id::text
            FROM to_delete t, attribute_definition ad
            WHERE ad.id = a.definition_id AND ad.entity_list_id = t.list_id AND a.value = t.id_to_delete::text
        SQL);

        // 2. Delete the duplicate rows from attribute_entity
        $this->addSql(<<<SQL
            WITH duplicates AS (
              SELECT
                value,
                ARRAY_AGG(id) AS all_ids
              FROM attribute_entity
              GROUP BY list_id, value
              HAVING COUNT(*) > 1
            ),
            to_delete AS (
              SELECT
                unnest(all_ids[2:]) AS id_to_delete
              FROM duplicates
            )
            DELETE FROM attribute_entity e
            USING to_delete t
            WHERE e.id = t.id_to_delete
        SQL);
        $this->addSql('CREATE UNIQUE INDEX list_value_uniq ON attribute_entity (list_id, value)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX list_value_uniq');
    }
}
