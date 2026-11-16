<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116172413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the multipart uploads by owner, counted before each new upload';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX multipart_upload_owner_created_idx ON multipart_upload (owner_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX multipart_upload_owner_created_idx');
    }
}
