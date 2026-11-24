<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Type of the files detected from their content, and files refused by the content check';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD detected_type VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE file ADD content_rejected BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP detected_type');
        $this->addSql('ALTER TABLE file DROP content_rejected');
    }
}
