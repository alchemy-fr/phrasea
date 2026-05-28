<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520171528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move file metadata to a separate table and link it with a foreign key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE file_metadata (id UUID NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN file_metadata.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN file_metadata.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN file_metadata.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE file ADD metadata_id UUID DEFAULT NULL');

        // Insert new UUIDs for file_metadata and map them to file.metadata_id
        $this->addSql('CREATE TEMP TABLE file_metadata_map (file_id UUID, metadata_id UUID) ON COMMIT DROP');
        $this->addSql('INSERT INTO file_metadata_map (file_id, metadata_id) SELECT f.id, gen_random_uuid() FROM file f WHERE f.metadata IS NOT NULL');
        $this->addSql('INSERT INTO file_metadata (id, metadata, created_at, updated_at) SELECT m.metadata_id, f.metadata, f.created_at, f.updated_at FROM file_metadata_map m JOIN file f ON f.id = m.file_id');
        $this->addSql('UPDATE file SET metadata_id = m.metadata_id FROM file_metadata_map m WHERE file.id = m.file_id');

        $this->addSql('ALTER TABLE file DROP metadata');
        $this->addSql('COMMENT ON COLUMN file.metadata_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610DC9EE959 FOREIGN KEY (metadata_id) REFERENCES file_metadata (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8C9F3610DC9EE959 ON file (metadata_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP CONSTRAINT FK_8C9F3610DC9EE959');
        $this->addSql('DROP TABLE file_metadata');
        $this->addSql('DROP INDEX UNIQ_8C9F3610DC9EE959');
        $this->addSql('ALTER TABLE file ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE file DROP metadata_id');
    }
}
