<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260518131121 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renames the field_type column to type in the attribute_definition table and updates the corresponding index.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX type_idx');
        $this->addSql('ALTER TABLE attribute_definition RENAME COLUMN field_type TO type');
        $this->addSql('CREATE INDEX type_idx ON attribute_definition (type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX type_idx');
        $this->addSql('ALTER TABLE attribute_definition RENAME COLUMN type TO field_type');
        $this->addSql('CREATE INDEX type_idx ON attribute_definition (field_type)');
    }
}
