<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116115317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Multipart uploads are used once: completed uploads are already consumed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE multipart_upload ADD consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE multipart_upload SET consumed_at = created_at WHERE complete = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE multipart_upload DROP consumed_at');
    }
}
