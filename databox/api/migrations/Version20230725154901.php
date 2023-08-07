<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230725154901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_token DROP CONSTRAINT fk_b6a2dd6819eb6921');
        $this->addSql('ALTER TABLE auth_code DROP CONSTRAINT fk_5933d02c19eb6921');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT fk_c74f219519eb6921');
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE auth_code');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP INDEX uniq_ace');
        $this->addSql('ALTER TABLE access_control_entry ADD parent_id VARCHAR(39) DEFAULT NULL');
        $this->addSql('CREATE INDEX parent_idx ON access_control_entry (parent_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ace ON access_control_entry (user_type, user_id, object_type, object_id, parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE access_token (id UUID NOT NULL, client_id VARCHAR(80) NOT NULL, token VARCHAR(255) NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_b6a2dd6819eb6921 ON access_token (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_b6a2dd685f37a13b ON access_token (token)');
        $this->addSql('COMMENT ON COLUMN access_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE auth_code (id UUID NOT NULL, client_id VARCHAR(80) NOT NULL, token VARCHAR(255) NOT NULL, redirect_uri TEXT NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_5933d02c19eb6921 ON auth_code (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_5933d02c5f37a13b ON auth_code (token)');
        $this->addSql('COMMENT ON COLUMN auth_code.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE refresh_token (id UUID NOT NULL, client_id VARCHAR(80) NOT NULL, token VARCHAR(255) NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_c74f219519eb6921 ON refresh_token (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_c74f21955f37a13b ON refresh_token (token)');
        $this->addSql('COMMENT ON COLUMN refresh_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE oauth_client (id VARCHAR(80) NOT NULL, random_id VARCHAR(255) NOT NULL, redirect_uris TEXT NOT NULL, secret VARCHAR(255) NOT NULL, allowed_grant_types TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, allowed_scopes JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN oauth_client.redirect_uris IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN oauth_client.allowed_grant_types IS \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT fk_b6a2dd6819eb6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE auth_code ADD CONSTRAINT fk_5933d02c19eb6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT fk_c74f219519eb6921 FOREIGN KEY (client_id) REFERENCES oauth_client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX parent_idx');
        $this->addSql('DROP INDEX uniq_ace');
        $this->addSql('ALTER TABLE access_control_entry DROP parent_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_ace ON access_control_entry (user_type, user_id, object_type, object_id)');
    }
}
