<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Share links may be protected by a password (share.password_hash)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE share ADD password_hash VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE share DROP password_hash');
    }
}
