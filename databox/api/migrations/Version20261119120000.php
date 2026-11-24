<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the UUID values of integration data, looked up by file ID when reading or removing a file';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX int_uuid_val_idx ON integration_data (value) WHERE (length(value) = 36)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX int_uuid_val_idx');
    }
}
