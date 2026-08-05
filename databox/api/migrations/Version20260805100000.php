<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add owner_id to entity_list, defaulting to the workspace owner';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entity_list ADD owner_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('UPDATE entity_list el SET owner_id = w.owner_id FROM workspace w WHERE w.id = el.workspace_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entity_list DROP owner_id');
    }
}
