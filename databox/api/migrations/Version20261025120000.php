<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional, recipient-bound capability for an AssetExport';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_export ADD delegate_user_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_export ADD delegation_hash VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_export DROP delegate_user_id');
        $this->addSql('ALTER TABLE asset_export DROP delegation_hash');
    }
}
