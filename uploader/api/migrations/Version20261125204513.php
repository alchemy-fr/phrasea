<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125204513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Commits: record when their target was notified, so that a retried commit notifies it again only if that failed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_commit ADD notify_dispatched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // The existing commits are taken as notified: a retry must not notify their target a second time
        $this->addSql('UPDATE asset_commit SET notify_dispatched_at = created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_commit DROP notify_dispatched_at');
    }
}
