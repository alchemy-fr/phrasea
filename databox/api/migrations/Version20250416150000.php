<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250416150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stories: bilateral OneToOne asset <-> collection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_2af5a5c717dfdc8');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2AF5A5C717DFDC8 ON asset (story_collection_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_2AF5A5C717DFDC8');
        $this->addSql('CREATE INDEX idx_2af5a5c717dfdc8 ON asset (story_collection_id)');
    }
}
