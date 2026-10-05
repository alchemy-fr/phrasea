<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713140456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add doc_unique_id to file table and checksum to file_metadata table, and create indexes for performance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD doc_unique_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN file.doc_unique_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX IDX_8C9F361082D40A1FDE6FDF9A ON file (workspace_id, checksum)');
        $this->addSql('CREATE INDEX IDX_8C9F361082D40A1F1F1EBF22 ON file (workspace_id, doc_unique_id)');
        $this->addSql('ALTER TABLE file_metadata ADD checksum VARCHAR(64) DEFAULT NULL');

        // Backfill existing rows so that Version20260713141851 can set the column NOT NULL.
        // The application computed hash('sha256', serialize($metadata)), which cannot be reproduced in SQL;
        // a SHA-256 of the JSON text is enough here: Version20260730100000 recomputes every checksum
        // in PHP and Version20260907230000 drops the column altogether.
        $this->addSql("UPDATE file_metadata SET checksum = encode(sha256(convert_to(metadata::text, 'UTF8')), 'hex') WHERE checksum IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_8C9F361082D40A1FDE6FDF9A');
        $this->addSql('DROP INDEX IDX_8C9F361082D40A1F1F1EBF22');
        $this->addSql('ALTER TABLE file DROP doc_unique_id');
        $this->addSql('ALTER TABLE file_metadata DROP checksum');
    }
}
