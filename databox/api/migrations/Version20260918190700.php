<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918190700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand IP field in changelog and action log to a length of 39';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_log ALTER COLUMN ip TYPE VARCHAR(39)');
        $this->addSql('ALTER TABLE change_log ALTER COLUMN ip TYPE VARCHAR(39)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_log ALTER COLUMN ip TYPE VARCHAR(32)');
        $this->addSql('ALTER TABLE change_log ALTER COLUMN ip TYPE VARCHAR(32)');
    }
}
