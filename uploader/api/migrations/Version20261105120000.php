<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the commit token, looked up on every AssetToken request';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX asset_commit_token_idx ON asset_commit (token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX asset_commit_token_idx');
    }
}
