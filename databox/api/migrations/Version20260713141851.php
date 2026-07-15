<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713141851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make checksum column in file_metadata table NOT NULL';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file_metadata ALTER checksum SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file_metadata ALTER checksum DROP NOT NULL');
    }
}
