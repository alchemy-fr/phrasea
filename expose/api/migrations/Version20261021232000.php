<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021232000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Publication archives are built by Expose: archive_path/archive_hash replace the Zippy columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication DROP zippy_id, DROP zippy_hash, ADD archive_path VARCHAR(255) DEFAULT NULL, ADD archive_hash VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE publication RENAME COLUMN config_include_download_terms_in_zippy TO config_include_download_terms_in_archive');
        $this->addSql('ALTER TABLE publication_profile RENAME COLUMN config_include_download_terms_in_zippy TO config_include_download_terms_in_archive');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication_profile RENAME COLUMN config_include_download_terms_in_archive TO config_include_download_terms_in_zippy');
        $this->addSql('ALTER TABLE publication RENAME COLUMN config_include_download_terms_in_archive TO config_include_download_terms_in_zippy');
        $this->addSql('ALTER TABLE publication DROP archive_path, DROP archive_hash, ADD zippy_id VARCHAR(36) DEFAULT NULL, ADD zippy_hash VARCHAR(32) DEFAULT NULL');
    }
}
