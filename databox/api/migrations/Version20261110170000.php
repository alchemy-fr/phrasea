<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Memory of the Elasticsearch populate passes, page by page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE populate_pass ADD stats JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE populate_pass DROP stats');
    }
}
