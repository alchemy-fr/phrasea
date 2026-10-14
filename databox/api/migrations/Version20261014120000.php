<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attribute definitions may enable asset annotations on their values (attribute_definition.annotations_enabled)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition ADD annotations_enabled BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition DROP annotations_enabled');
    }
}
