<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DBAL 4: drop the DC2Type column comments of asset_embedding';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN asset_embedding.id IS NULL');
        $this->addSql('COMMENT ON COLUMN asset_embedding.asset_id IS NULL');
        $this->addSql('COMMENT ON COLUMN asset_embedding.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN asset_embedding.updated_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN asset_embedding.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_embedding.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_embedding.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_embedding.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
