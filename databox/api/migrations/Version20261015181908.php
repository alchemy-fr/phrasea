<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015181908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow attribute entities without an entity type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity ALTER entity_type_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity ALTER entity_type_id SET NOT NULL');
    }
}
