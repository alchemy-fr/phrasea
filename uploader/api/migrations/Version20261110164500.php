<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110164500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Owner of the multipart uploads';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE multipart_upload ADD owner_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE multipart_upload DROP owner_id');
    }
}
