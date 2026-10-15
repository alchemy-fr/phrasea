<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Faces become attribute values (attribute.embedding) of person entities (attribute_entity.note, same name allowed twice in a list); drop asset_face. Reverting recreates the unique index on (list_id, value), which fails while a list holds homonyms';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute ADD embedding JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_entity ADD note VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX list_value_uniq');
        $this->addSql('DROP TABLE asset_face');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE asset_face (id UUID NOT NULL, asset_id UUID NOT NULL, box JSON NOT NULL, position SMALLINT NOT NULL, confidence DOUBLE PRECISION NOT NULL, vector JSON NOT NULL, model VARCHAR(100) NOT NULL, dims SMALLINT NOT NULL, identity_name VARCHAR(255) DEFAULT NULL, identity_confidence DOUBLE PRECISION DEFAULT NULL, identity_origin VARCHAR(20) DEFAULT NULL, reference_id UUID DEFAULT NULL, details JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_asset_face_asset ON asset_face (asset_id)');
        $this->addSql('CREATE INDEX idx_asset_face_identity ON asset_face (identity_name)');
        $this->addSql('CREATE INDEX idx_asset_face_reference ON asset_face (reference_id)');
        $this->addSql('COMMENT ON COLUMN asset_face.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_face.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_face.reference_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_face.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_face.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE asset_face ADD CONSTRAINT FK_asset_face_asset FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_face ADD CONSTRAINT FK_asset_face_reference FOREIGN KEY (reference_id) REFERENCES asset_face (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX list_value_uniq ON attribute_entity (list_id, value)');
        $this->addSql('ALTER TABLE attribute_entity DROP note');
        $this->addSql('ALTER TABLE attribute DROP embedding');
    }
}
