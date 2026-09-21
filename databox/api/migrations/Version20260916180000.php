<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Denormalize integration_token.has_refresh_token from the token JSON and index it with expires_at for the renewal cron';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_token ADD has_refresh_token BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("UPDATE integration_token SET has_refresh_token = true WHERE COALESCE(token->>'refresh_token', '') <> ''");
        $this->addSql('CREATE INDEX renewable_token ON integration_token (has_refresh_token, expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX renewable_token');
        $this->addSql('ALTER TABLE integration_token DROP has_refresh_token');
    }
}
