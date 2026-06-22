<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260622132343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin_task table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_task (id UUID NOT NULL, task VARCHAR(255) NOT NULL, payload JSON NOT NULL, output TEXT DEFAULT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, progress BIGINT DEFAULT NULL, item_total BIGINT DEFAULT NULL, estimated VARCHAR(255) DEFAULT NULL, remaining VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX admin_task_task_idx ON admin_task (task)');
        $this->addSql('COMMENT ON COLUMN admin_task.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN admin_task.ended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN admin_task.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_task');
    }
}
