<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attribute definitions bound to a range of hierarchy depths, no hierarchy branch';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition ADD entity_hierarchy_depth_min INT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD entity_hierarchy_depth_max INT DEFAULT NULL');
        $this->addSql('UPDATE attribute_definition SET entity_hierarchy_depth_min = entity_hierarchy_depth, entity_hierarchy_depth_max = entity_hierarchy_depth');
        $this->addSql('ALTER TABLE attribute_definition DROP entity_hierarchy_depth');

        $this->addSql('ALTER TABLE attribute_definition DROP CONSTRAINT FK_6C5628BD839C9F7D');
        $this->addSql('DROP INDEX IDX_6C5628BD839C9F7D');
        $this->addSql('ALTER TABLE attribute_definition DROP entity_hierarchy_branch_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition ADD entity_hierarchy_branch_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD CONSTRAINT FK_6C5628BD839C9F7D FOREIGN KEY (entity_hierarchy_branch_id) REFERENCES attribute_entity (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6C5628BD839C9F7D ON attribute_definition (entity_hierarchy_branch_id)');

        // A range keeps its lowest depth
        $this->addSql('ALTER TABLE attribute_definition ADD entity_hierarchy_depth INT DEFAULT NULL');
        $this->addSql('UPDATE attribute_definition SET entity_hierarchy_depth = COALESCE(entity_hierarchy_depth_min, entity_hierarchy_depth_max)');
        $this->addSql('ALTER TABLE attribute_definition DROP entity_hierarchy_depth_max');
        $this->addSql('ALTER TABLE attribute_definition DROP entity_hierarchy_depth_min');
    }
}
