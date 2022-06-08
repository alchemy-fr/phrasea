<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220608093541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_class ADD editable BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE attribute_class ADD public BOOLEAN NOT NULL');
        $this->addSql('DROP INDEX public_idx');
        $this->addSql('DROP INDEX public_searchable_idx');
        $this->addSql('ALTER TABLE attribute_definition DROP editable');
        $this->addSql('ALTER TABLE attribute_definition DROP public');

        $this->addSql('INSERT INTO attribute_class (
                             id, 
                             workspace_id, 
                             name,
                             editable,
                             public,
                             created_at
                             ) (SELECT 
                                    id,
                                    id,
                                    \'Default\',
                                    true,
                                    true,
                                    NOW()
                                FROM workspace)');
        $this->addSql('UPDATE attribute_definition ad SET class_id = (SELECT at.id FROM attribute_class at WHERE at.workspace_id = ad.workspace_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE attribute_definition ADD editable BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD public BOOLEAN NOT NULL');
        $this->addSql('CREATE INDEX public_idx ON attribute_definition (public)');
        $this->addSql('CREATE INDEX public_searchable_idx ON attribute_definition (searchable, public)');
        $this->addSql('ALTER TABLE attribute_class DROP editable');
        $this->addSql('ALTER TABLE attribute_class DROP public');
    }
}
