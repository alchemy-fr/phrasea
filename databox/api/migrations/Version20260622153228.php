<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260622153228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add operation_task table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE operation_task (id UUID NOT NULL, task VARCHAR(255) NOT NULL, payload JSON NOT NULL, status SMALLINT NOT NULL, output TEXT DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, progress BIGINT DEFAULT NULL, item_total BIGINT DEFAULT NULL, estimated VARCHAR(255) DEFAULT NULL, remaining VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id VARCHAR(36) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX operation_task_idx ON operation_task (task)');
        $this->addSql('CREATE INDEX operation_status_idx ON operation_task (status)');
        $this->addSql('COMMENT ON COLUMN operation_task.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN operation_task.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN operation_task.ended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN operation_task.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE operation_task');
    }
}
