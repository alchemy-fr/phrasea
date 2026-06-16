<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616125142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_index_pass ADD estimated VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_index_pass ADD remaining VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_index_pass DROP estimated');
        $this->addSql('ALTER TABLE asset_index_pass DROP remaining');
    }
}
