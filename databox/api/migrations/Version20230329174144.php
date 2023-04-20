<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230329174144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE job_state (id VARCHAR(36) NOT NULL, workflow_id VARCHAR(36) DEFAULT NULL, state TEXT NOT NULL, job_id VARCHAR(100) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A2CF6F8B2C7C2CBA ON job_state (workflow_id)');
        $this->addSql('CREATE INDEX workflow_job ON job_state (workflow_id, job_id)');
        $this->addSql('CREATE TABLE workflow_state (id VARCHAR(36) NOT NULL, state TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE job_state ADD CONSTRAINT FK_A2CF6F8B2C7C2CBA FOREIGN KEY (workflow_id) REFERENCES workflow_state (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE job_state DROP CONSTRAINT FK_A2CF6F8B2C7C2CBA');
        $this->addSql('DROP TABLE job_state');
        $this->addSql('DROP TABLE workflow_state');
    }
}
