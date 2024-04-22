<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200512114816 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE publication_profile (id UUID NOT NULL, name VARCHAR(150) NOT NULL, owner_id VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, config_enabled BOOLEAN NOT NULL, config_urls JSON NOT NULL, config_copyright_text TEXT NOT NULL, config_css TEXT NOT NULL, config_layout VARCHAR(20) NOT NULL, config_theme VARCHAR(30) DEFAULT NULL, config_publicly_listed BOOLEAN NOT NULL, config_begins_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, config_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, config_security_method VARCHAR(20) DEFAULT NULL, config_security_options JSON NOT NULL, config_terms_text TEXT NOT NULL, config_terms_url VARCHAR(255) NOT NULL, config_terms_must_accept BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN publication_profile.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN publication_profile.config_security_options IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE publication DROP CONSTRAINT fk_af3c6779922726e9');
        $this->addSql('DROP INDEX idx_af3c6779922726e9');

        $this->addSql('ALTER TABLE publication RENAME COLUMN enabled TO config_enabled');
        $this->addSql('ALTER TABLE publication RENAME COLUMN publicly_listed TO config_publicly_listed');
        $this->addSql('ALTER TABLE publication RENAME COLUMN begins_at TO config_begins_at');
        $this->addSql('ALTER TABLE publication RENAME COLUMN expires_at TO config_expires_at');
        $this->addSql('ALTER TABLE publication RENAME COLUMN theme TO config_theme');
        $this->addSql('ALTER TABLE publication RENAME COLUMN layout TO config_layout');


        $this->addSql('ALTER TABLE publication ADD config_urls JSON NULL');
        $this->addSql('UPDATE publication SET config_urls = \'[]\'');
        $this->addSql('ALTER TABLE publication ALTER COLUMN config_urls SET NOT NULL');

        $this->addSql('ALTER TABLE publication ADD config_copyright_text TEXT NULL');
        $this->addSql('ALTER TABLE publication ADD config_css TEXT NULL');
        $this->addSql('ALTER TABLE publication ADD config_terms_text TEXT NULL');
        $this->addSql('ALTER TABLE publication ADD config_terms_url VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE publication ADD config_terms_must_accept BOOLEAN NULL');
        $this->addSql('UPDATE publication SET config_terms_must_accept = false');
        $this->addSql('ALTER TABLE publication ALTER COLUMN config_terms_must_accept SET NOT NULL');

        $this->addSql('ALTER TABLE publication ADD profile_id UUID NULL');
        $this->addSql('ALTER TABLE publication RENAME COLUMN cover_id TO config_cover_id');
        $this->addSql('ALTER TABLE publication RENAME COLUMN security_method TO config_security_method');
        $this->addSql('ALTER TABLE publication RENAME COLUMN security_options TO config_security_options');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779CCFA12B8 FOREIGN KEY (profile_id) REFERENCES publication_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AF3C6779CCFA12B8 ON publication (profile_id)');

        $this->addSql('ALTER TABLE publication DROP config_cover_id;');
        $this->addSql('ALTER TABLE publication ALTER profile_id TYPE UUID;');
        $this->addSql('ALTER TABLE publication ALTER profile_id DROP DEFAULT;');
        $this->addSql('COMMENT ON COLUMN publication.profile_id IS \'(DC2Type:uuid)\';');
        $this->addSql('ALTER TABLE publication_profile ALTER config_copyright_text DROP NOT NULL;');
        $this->addSql('ALTER TABLE publication_profile ALTER config_css DROP NOT NULL;');
        $this->addSql('ALTER TABLE publication_profile ALTER config_terms_text DROP NOT NULL;');
        $this->addSql('ALTER TABLE publication_profile ALTER config_terms_url DROP NOT NULL;');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE publication DROP CONSTRAINT FK_AF3C6779CCFA12B8');
        $this->addSql('DROP TABLE publication_profile');
        $this->addSql('DROP INDEX IDX_AF3C6779CCFA12B8');
        $this->addSql('ALTER TABLE publication ADD enabled BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE publication ADD publicly_listed BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE publication ADD begins_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE publication DROP config_enabled');
        $this->addSql('ALTER TABLE publication DROP config_urls');
        $this->addSql('ALTER TABLE publication DROP config_copyright_text');
        $this->addSql('ALTER TABLE publication DROP config_css');
        $this->addSql('ALTER TABLE publication DROP config_publicly_listed');
        $this->addSql('ALTER TABLE publication DROP config_begins_at');
        $this->addSql('ALTER TABLE publication DROP config_expires_at');
        $this->addSql('ALTER TABLE publication DROP config_terms_text');
        $this->addSql('ALTER TABLE publication DROP config_terms_url');
        $this->addSql('ALTER TABLE publication DROP config_terms_must_accept');
        $this->addSql('ALTER TABLE publication RENAME COLUMN profile_id TO cover_id');
        $this->addSql('ALTER TABLE publication RENAME COLUMN config_layout TO layout');
        $this->addSql('ALTER TABLE publication RENAME COLUMN config_theme TO theme');
        $this->addSql('ALTER TABLE publication RENAME COLUMN config_security_method TO security_method');
        $this->addSql('ALTER TABLE publication RENAME COLUMN config_security_options TO security_options');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT fk_af3c6779922726e9 FOREIGN KEY (cover_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_af3c6779922726e9 ON publication (cover_id)');
    }
}
