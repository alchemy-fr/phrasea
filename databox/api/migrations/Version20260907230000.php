<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the application metadata overrides of a file in their own table (file_metadata becomes immutable, its checksum is dropped), and let an attribute definition restrict the renditions it is written into';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE file_overridden_metadata (id UUID NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE file ADD overridden_metadata_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F361019A0AB55 FOREIGN KEY (overridden_metadata_id) REFERENCES file_overridden_metadata (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8C9F361019A0AB55 ON file (overridden_metadata_id)');

        // FileMetadata is now immutable once read: its checksum had no purpose left
        $this->addSql('ALTER TABLE file_metadata DROP checksum');

        $this->addSql('CREATE TABLE attribute_definition_write_rendition (attribute_definition_id UUID NOT NULL, rendition_definition_id UUID NOT NULL, PRIMARY KEY(attribute_definition_id, rendition_definition_id))');
        $this->addSql('CREATE INDEX IDX_ADWR_ATTRIBUTE ON attribute_definition_write_rendition (attribute_definition_id)');
        $this->addSql('CREATE INDEX IDX_ADWR_RENDITION ON attribute_definition_write_rendition (rendition_definition_id)');
        $this->addSql('ALTER TABLE attribute_definition_write_rendition ADD CONSTRAINT FK_ADWR_ATTRIBUTE FOREIGN KEY (attribute_definition_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute_definition_write_rendition ADD CONSTRAINT FK_ADWR_RENDITION FOREIGN KEY (rendition_definition_id) REFERENCES rendition_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE attribute_definition_write_rendition');

        $this->addSql("ALTER TABLE file_metadata ADD checksum VARCHAR(64) DEFAULT '' NOT NULL");

        $this->addSql('ALTER TABLE file DROP CONSTRAINT FK_8C9F361019A0AB55');
        $this->addSql('DROP INDEX UNIQ_8C9F361019A0AB55');
        $this->addSql('ALTER TABLE file DROP overridden_metadata_id');
        $this->addSql('DROP TABLE file_overridden_metadata');
    }
}
