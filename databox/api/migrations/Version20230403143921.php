<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230403143921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE job_state ADD started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE job_state ADD ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE job_state ADD status SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE job_state RENAME COLUMN created_at TO triggered_at');
        $this->addSql('COMMENT ON COLUMN job_state.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN job_state.ended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE workflow_state ADD ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE workflow_state ADD status SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE workflow_state ADD name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE workflow_state RENAME COLUMN created_at TO started_at');
        $this->addSql('COMMENT ON COLUMN workflow_state.ended_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE workflow_state DROP ended_at');
        $this->addSql('ALTER TABLE workflow_state DROP status');
        $this->addSql('ALTER TABLE workflow_state DROP name');
        $this->addSql('ALTER TABLE workflow_state RENAME COLUMN started_at TO created_at');
        $this->addSql('ALTER TABLE job_state DROP started_at');
        $this->addSql('ALTER TABLE job_state DROP ended_at');
        $this->addSql('ALTER TABLE job_state DROP status');
        $this->addSql('ALTER TABLE job_state RENAME COLUMN triggered_at TO created_at');
    }
}
