<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Default display profiles (profile.default_profile)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD default_profile BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP default_profile');
    }
}
