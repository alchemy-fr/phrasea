<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702121321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to asset table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset ADD status SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE asset ALTER status DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset DROP status');
    }
}
