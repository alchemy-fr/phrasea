<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929120001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist the server-decided part size of multipart uploads so that a resumed upload keeps the same slicing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE multipart_upload ADD chunk_size BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE multipart_upload DROP chunk_size');
    }
}
