<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Entity hierarchies: parent, depth and placeholder on attribute_entity, root on entity_list, depth binding on attribute_definition';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity ADD parent_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD hierarchy_depth INT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD placeholder BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD CONSTRAINT FK_2CC96A32727ACA70 FOREIGN KEY (parent_id) REFERENCES attribute_entity (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX entity_parent_idx ON attribute_entity (list_id, parent_id, position)');
        $this->addSql('CREATE INDEX entity_depth_idx ON attribute_entity (list_id, hierarchy_depth)');
        $this->addSql('CREATE INDEX IDX_2CC96A32727ACA70 ON attribute_entity (parent_id)');

        $this->addSql('ALTER TABLE entity_list ADD root_entity_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE entity_list ADD CONSTRAINT FK_C044133C837677A FOREIGN KEY (root_entity_id) REFERENCES attribute_entity (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C044133C837677A ON entity_list (root_entity_id)');

        $this->addSql('ALTER TABLE attribute_definition ADD entity_hierarchy_depth INT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD auto_fill_parents BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD entity_hierarchy_branch_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD CONSTRAINT FK_6C5628BD839C9F7D FOREIGN KEY (entity_hierarchy_branch_id) REFERENCES attribute_entity (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6C5628BD839C9F7D ON attribute_definition (entity_hierarchy_branch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition DROP CONSTRAINT FK_6C5628BD839C9F7D');
        $this->addSql('DROP INDEX IDX_6C5628BD839C9F7D');
        $this->addSql('ALTER TABLE attribute_definition DROP entity_hierarchy_branch_id');
        $this->addSql('ALTER TABLE attribute_definition DROP auto_fill_parents');
        $this->addSql('ALTER TABLE attribute_definition DROP entity_hierarchy_depth');

        $this->addSql('ALTER TABLE entity_list DROP CONSTRAINT FK_C044133C837677A');
        $this->addSql('DROP INDEX UNIQ_C044133C837677A');
        $this->addSql('ALTER TABLE entity_list DROP root_entity_id');

        $this->addSql('ALTER TABLE attribute_entity DROP CONSTRAINT FK_2CC96A32727ACA70');
        $this->addSql('DROP INDEX IDX_2CC96A32727ACA70');
        $this->addSql('DROP INDEX entity_depth_idx');
        $this->addSql('DROP INDEX entity_parent_idx');
        $this->addSql('ALTER TABLE attribute_entity DROP placeholder');
        $this->addSql('ALTER TABLE attribute_entity DROP hierarchy_depth');
        $this->addSql('ALTER TABLE attribute_entity DROP parent_id');
    }
}
