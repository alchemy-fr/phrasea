<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Entity hierarchies: parent, depth and placeholder of the entities, depth range and parent filling of the attribute definitions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity ADD parent_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD hierarchy_depth INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD placeholder BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD CONSTRAINT FK_2CC96A32727ACA70 FOREIGN KEY (parent_id) REFERENCES attribute_entity (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_2CC96A32727ACA70 ON attribute_entity (parent_id)');
        $this->addSql('CREATE INDEX entity_parent_idx ON attribute_entity (list_id, parent_id, position)');
        $this->addSql('CREATE INDEX entity_depth_idx ON attribute_entity (list_id, hierarchy_depth)');

        $this->addSql('ALTER TABLE attribute_definition ADD entity_hierarchy_depth_min INT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD entity_hierarchy_depth_max INT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD auto_fill_parents BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition DROP auto_fill_parents');
        $this->addSql('ALTER TABLE attribute_definition DROP entity_hierarchy_depth_max');
        $this->addSql('ALTER TABLE attribute_definition DROP entity_hierarchy_depth_min');

        $this->addSql('ALTER TABLE attribute_entity DROP CONSTRAINT FK_2CC96A32727ACA70');
        $this->addSql('DROP INDEX entity_depth_idx');
        $this->addSql('DROP INDEX entity_parent_idx');
        $this->addSql('DROP INDEX IDX_2CC96A32727ACA70');
        $this->addSql('ALTER TABLE attribute_entity DROP placeholder');
        $this->addSql('ALTER TABLE attribute_entity DROP hierarchy_depth');
        $this->addSql('ALTER TABLE attribute_entity DROP parent_id');
    }
}
