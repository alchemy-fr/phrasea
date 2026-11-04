<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move the detailed file analysis into its own table (file_analysis); the file only keeps the analysis date and whether it was accepted';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE file_analysis (id UUID NOT NULL, status VARCHAR(20) NOT NULL, hash VARCHAR(32) DEFAULT NULL, message TEXT DEFAULT NULL, results JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN file_analysis.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN file_analysis.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE file ADD analysis_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE file ADD analyzed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE file ADD accepted BOOLEAN DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN file.analysis_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN file.analyzed_at IS \'(DC2Type:datetime_immutable)\'');

        // Data: a file analyzed with details gets a file_analysis row (reusing the file id as row id),
        // every analyzed file (including "no analysis needed", stored as [] or {}) gets its date and outcome.
        $this->addSql(<<<'SQL'
            INSERT INTO file_analysis (id, status, hash, message, results, created_at)
            SELECT id,
                   COALESCE(analysis->>'status', 'success'),
                   analysis->>'hash',
                   analysis->>'message',
                   COALESCE(analysis->'results', '[]'::json),
                   updated_at
            FROM file
            WHERE analysis IS NOT NULL
              AND json_typeof(analysis) = 'object'
              AND analysis::text <> '{}'
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE file SET
                analysis_id = CASE WHEN json_typeof(analysis) = 'object' AND analysis::text <> '{}' THEN id END,
                analyzed_at = updated_at,
                accepted = (
                    json_typeof(analysis) <> 'object'
                    OR analysis::text = '{}'
                    OR analysis->>'status' IN ('success', 'skipped', 'bypassed')
                )
            WHERE analysis IS NOT NULL
            SQL);

        $this->addSql('ALTER TABLE file DROP analysis');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F36107941003F FOREIGN KEY (analysis_id) REFERENCES file_analysis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8C9F36107941003F ON file (analysis_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD analysis JSON DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE file SET analysis = json_strip_nulls(json_build_object(
                'status', a.status,
                'results', CASE WHEN a.results::text <> '[]' THEN a.results END,
                'hash', a.hash,
                'message', a.message
            ))
            FROM file_analysis a
            WHERE a.id = file.analysis_id
            SQL);
        $this->addSql('UPDATE file SET analysis = \'[]\' WHERE analysis IS NULL AND analyzed_at IS NOT NULL');

        $this->addSql('ALTER TABLE file DROP CONSTRAINT FK_8C9F36107941003F');
        $this->addSql('DROP INDEX UNIQ_8C9F36107941003F');
        $this->addSql('ALTER TABLE file DROP analysis_id');
        $this->addSql('ALTER TABLE file DROP analyzed_at');
        $this->addSql('ALTER TABLE file DROP accepted');
        $this->addSql('DROP TABLE file_analysis');
    }
}
