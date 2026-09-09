<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add asset_embedding table (similarity vectors)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE asset_embedding (id UUID NOT NULL, asset_id UUID NOT NULL, vector JSON NOT NULL, model VARCHAR(100) NOT NULL, dims SMALLINT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_asset_embedding_asset ON asset_embedding (asset_id)');
        $this->addSql('COMMENT ON COLUMN asset_embedding.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_embedding.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_embedding.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_embedding.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE asset_embedding ADD CONSTRAINT FK_asset_embedding_asset FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE asset_embedding');
    }
}
