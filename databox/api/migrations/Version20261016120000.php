<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flag the URL files whose content must be imported to the main storage (file.import_pending)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD import_pending BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP import_pending');
    }
}
