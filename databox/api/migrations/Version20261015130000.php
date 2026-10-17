<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parent-child hierarchy to attribute entities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity ADD parent_id UUID DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN attribute_entity.parent_id IS '(DC2Type:uuid)'");
        $this->addSql('CREATE INDEX idx_attribute_entity_parent ON attribute_entity (parent_id)');
        $this->addSql('ALTER TABLE attribute_entity ADD CONSTRAINT fk_attribute_entity_parent FOREIGN KEY (parent_id) REFERENCES attribute_entity (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity DROP CONSTRAINT fk_attribute_entity_parent');
        $this->addSql('DROP INDEX idx_attribute_entity_parent');
        $this->addSql('ALTER TABLE attribute_entity DROP parent_id');
    }
}
