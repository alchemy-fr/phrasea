<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240605143658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE integration_data (id UUID NOT NULL, integration_id UUID NOT NULL, name VARCHAR(100) DEFAULT NULL, key_id VARCHAR(100) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, value TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, object_type VARCHAR(15) DEFAULT NULL, object_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_986DCE789E82DDEA ON integration_data (integration_id)');
        $this->addSql('CREATE INDEX int_obj_idx ON integration_data (integration_id, object_type, object_id)');
        $this->addSql('CREATE INDEX int_nam_idx ON integration_data (integration_id, name)');
        $this->addSql('COMMENT ON COLUMN integration_data.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.integration_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN integration_data.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE integration_data ADD CONSTRAINT FK_986DCE789E82DDEA FOREIGN KEY (integration_id) REFERENCES workspace_integration (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_file_data DROP CONSTRAINT fk_64c24aae9e82ddea');
        $this->addSql('ALTER TABLE integration_file_data DROP CONSTRAINT fk_64c24aae232d562b');
        $this->addSql('ALTER TABLE integration_basket_data DROP CONSTRAINT fk_1282a7f39e82ddea');
        $this->addSql('ALTER TABLE integration_basket_data DROP CONSTRAINT fk_1282a7f3232d562b');

        $this->addSql('INSERT INTO integration_data (id, integration_id, name, key_id, user_id, "value", created_at, updated_at, object_type, object_id) SELECT id, integration_id, name, key_id, user_id, value, created_at, updated_at, \'file\', object_id FROM integration_file_data');
        $this->addSql('INSERT INTO integration_data (id, integration_id, name, key_id, user_id, "value", created_at, updated_at, object_type, object_id) SELECT id, integration_id, name, key_id, user_id, value, created_at, updated_at, \'basket\', object_id FROM integration_basket_data');

        $this->addSql('DROP TABLE integration_file_data');
        $this->addSql('DROP TABLE integration_basket_data');
    }

    public function down(Schema $schema): void
    {
    }
}
