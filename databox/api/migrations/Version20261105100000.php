<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Entity lists flagged as hierarchies: the lists having one already';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE entity_list SET options = (COALESCE(options::jsonb, '{}'::jsonb) || '{"h": true}'::jsonb)::json
            WHERE id IN (
                SELECT list_id FROM attribute_entity WHERE parent_id IS NOT NULL OR placeholder = true
                UNION
                SELECT entity_list_id FROM attribute_definition
                WHERE entity_list_id IS NOT NULL
                  AND (entity_hierarchy_depth_min IS NOT NULL OR entity_hierarchy_depth_max IS NOT NULL)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE entity_list SET options = (options::jsonb - 'h')::json WHERE options IS NOT NULL");
    }
}
