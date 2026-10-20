<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Similarity vectors (asset_embedding) and face recognition: faces are attribute values (attribute.embedding) of person entities (attribute_entity.note, same name allowed twice in a list). Reverting recreates the unique index on (list_id, value), which fails while a list holds homonyms';
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
        $this->addSql('ALTER TABLE attribute ADD embedding JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD note VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX list_value_uniq');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX list_value_uniq ON attribute_entity (list_id, value)');
        $this->addSql('ALTER TABLE attribute_entity DROP note');
        $this->addSql('ALTER TABLE attribute DROP embedding');
        $this->addSql('DROP TABLE asset_embedding');
    }
}
