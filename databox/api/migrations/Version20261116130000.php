<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Populate run of each populate pass';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE populate_pass ADD run_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE populate_pass DROP run_id');
    }
}
