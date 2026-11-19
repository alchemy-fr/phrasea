<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116184801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the file and asset import paths, looked up before each storage object deletion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX file_path_idx ON file (path) WHERE (length(path) < 512)');
        $this->addSql('CREATE INDEX asset_import_source_path_idx ON asset_import (source_path)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX asset_import_source_path_idx');
        $this->addSql('DROP INDEX file_path_idx');
    }
}
