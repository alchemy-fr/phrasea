<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230418154305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE job_state DROP CONSTRAINT fk_a2cf6f8b2c7c2cba');
        $this->addSql('DROP TABLE workflow_state');
        $this->addSql('DROP TABLE job_state');
        $this->addSql('ALTER TABLE attribute_definition RENAME COLUMN initializers TO initial_values');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE workflow_state (id VARCHAR(36) NOT NULL, state TEXT NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status SMALLINT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN workflow_state.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN workflow_state.ended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE job_state (id VARCHAR(36) NOT NULL, workflow_id VARCHAR(36) DEFAULT NULL, state TEXT NOT NULL, job_id VARCHAR(100) NOT NULL, triggered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status SMALLINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX workflow_job ON job_state (workflow_id, job_id)');
        $this->addSql('CREATE INDEX idx_a2cf6f8b2c7c2cba ON job_state (workflow_id)');
        $this->addSql('COMMENT ON COLUMN job_state.triggered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN job_state.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN job_state.ended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE job_state ADD CONSTRAINT fk_a2cf6f8b2c7c2cba FOREIGN KEY (workflow_id) REFERENCES workflow_state (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute_definition RENAME COLUMN initial_values TO initializers');
    }
}
