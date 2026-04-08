<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408081354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change attribute field type from "size" to "filesize"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE attribute_definition SET field_type = 'filesize' WHERE field_type = 'size'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE attribute_definition SET field_type = 'size' WHERE field_type = 'filesize'");
    }
}
