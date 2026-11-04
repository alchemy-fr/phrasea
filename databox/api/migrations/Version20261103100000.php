<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Entity hierarchies become forests: no root entity on entity_list, every entity without parent at depth 0';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entity_list DROP CONSTRAINT FK_C044133C837677A');
        $this->addSql('DROP INDEX UNIQ_C044133C837677A');
        $this->addSql('ALTER TABLE entity_list DROP root_entity_id');

        // The former unclassified entities (and their branches) join the top level
        $this->addSql(<<<'SQL'
            WITH RECURSIVE tree(id, depth) AS (
                SELECT id, 0 FROM attribute_entity WHERE parent_id IS NULL
                UNION ALL
                SELECT c.id, tree.depth + 1 FROM attribute_entity c INNER JOIN tree ON c.parent_id = tree.id
            )
            UPDATE attribute_entity e SET hierarchy_depth = tree.depth FROM tree WHERE e.id = tree.id
            SQL);
        $this->addSql('UPDATE attribute_entity SET hierarchy_depth = 0 WHERE hierarchy_depth IS NULL');
        $this->addSql('ALTER TABLE attribute_entity ALTER hierarchy_depth SET DEFAULT 0');
        $this->addSql('ALTER TABLE attribute_entity ALTER hierarchy_depth SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity ALTER hierarchy_depth DROP NOT NULL');
        $this->addSql('ALTER TABLE attribute_entity ALTER hierarchy_depth DROP DEFAULT');

        $this->addSql('ALTER TABLE entity_list ADD root_entity_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE entity_list ADD CONSTRAINT FK_C044133C837677A FOREIGN KEY (root_entity_id) REFERENCES attribute_entity (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C044133C837677A ON entity_list (root_entity_id)');
    }
}
