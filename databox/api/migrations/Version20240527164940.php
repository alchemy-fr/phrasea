<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240527164940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE integration_basket_data (id UUID NOT NULL, integration_id UUID NOT NULL, object_id UUID NOT NULL, name VARCHAR(100) DEFAULT NULL, key_id VARCHAR(100) DEFAULT NULL, value TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1282A7F39E82DDEA ON integration_basket_data (integration_id)');
        $this->addSql('CREATE INDEX IDX_1282A7F3232D562B ON integration_basket_data (object_id)');
        $this->addSql('CREATE INDEX int_object_idx ON integration_basket_data (integration_id, object_id)');
        $this->addSql('COMMENT ON COLUMN integration_basket_data.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_basket_data.integration_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_basket_data.object_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_basket_data.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN integration_basket_data.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE integration_file_data (id UUID NOT NULL, integration_id UUID NOT NULL, object_id UUID NOT NULL, name VARCHAR(100) DEFAULT NULL, key_id VARCHAR(100) DEFAULT NULL, value TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_64C24AAE9E82DDEA ON integration_file_data (integration_id)');
        $this->addSql('CREATE INDEX IDX_64C24AAE232D562B ON integration_file_data (object_id)');
        $this->addSql('CREATE INDEX int_obj_name_idx ON integration_file_data (integration_id, object_id, name)');
        $this->addSql('COMMENT ON COLUMN integration_file_data.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_file_data.integration_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_file_data.object_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_file_data.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN integration_file_data.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE integration_token (id UUID NOT NULL, integration_id UUID NOT NULL, user_id UUID DEFAULT NULL, token JSON NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D567F6EA9E82DDEA ON integration_token (integration_id)');
        $this->addSql('CREATE INDEX user_token ON integration_token (integration_id, user_id)');
        $this->addSql('COMMENT ON COLUMN integration_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_token.integration_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_token.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_token.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN integration_token.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE integration_basket_data ADD CONSTRAINT FK_1282A7F39E82DDEA FOREIGN KEY (integration_id) REFERENCES workspace_integration (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_basket_data ADD CONSTRAINT FK_1282A7F3232D562B FOREIGN KEY (object_id) REFERENCES basket (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_file_data ADD CONSTRAINT FK_64C24AAE9E82DDEA FOREIGN KEY (integration_id) REFERENCES workspace_integration (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_file_data ADD CONSTRAINT FK_64C24AAE232D562B FOREIGN KEY (object_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_token ADD CONSTRAINT FK_D567F6EA9E82DDEA FOREIGN KEY (integration_id) REFERENCES workspace_integration (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_data DROP CONSTRAINT fk_986dce789e82ddea');
        $this->addSql('ALTER TABLE integration_data DROP CONSTRAINT fk_986dce7893cb796c');

        $this->addSql('INSERT INTO integration_file_data (id, integration_id, object_id, name, key_id, value, created_at, updated_at) SELECT id, integration_id, file_id, name, key_id, value, created_at, updated_at FROM integration_data');

        $this->addSql('DROP TABLE integration_data');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE integration_data (id UUID NOT NULL, integration_id UUID NOT NULL, file_id UUID DEFAULT NULL, name VARCHAR(100) NOT NULL, key_id VARCHAR(100) DEFAULT NULL, value TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX name ON integration_data (integration_id, file_id, name)');
        $this->addSql('CREATE INDEX idx_986dce7893cb796c ON integration_data (file_id)');
        $this->addSql('CREATE INDEX idx_986dce789e82ddea ON integration_data (integration_id)');
        $this->addSql('COMMENT ON COLUMN integration_data.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.integration_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.file_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE integration_data ADD CONSTRAINT fk_986dce789e82ddea FOREIGN KEY (integration_id) REFERENCES workspace_integration (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_data ADD CONSTRAINT fk_986dce7893cb796c FOREIGN KEY (file_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_basket_data DROP CONSTRAINT FK_1282A7F39E82DDEA');
        $this->addSql('ALTER TABLE integration_basket_data DROP CONSTRAINT FK_1282A7F3232D562B');
        $this->addSql('ALTER TABLE integration_file_data DROP CONSTRAINT FK_64C24AAE9E82DDEA');
        $this->addSql('ALTER TABLE integration_file_data DROP CONSTRAINT FK_64C24AAE232D562B');
        $this->addSql('ALTER TABLE integration_token DROP CONSTRAINT FK_D567F6EA9E82DDEA');
        $this->addSql('DROP TABLE integration_basket_data');
        $this->addSql('DROP TABLE integration_file_data');
        $this->addSql('DROP TABLE integration_token');
    }
}
