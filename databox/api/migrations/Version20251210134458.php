<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210134458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE change_log (id UUID NOT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip VARCHAR(32) DEFAULT NULL, meta JSON NOT NULL, action SMALLINT NOT NULL, object_type SMALLINT DEFAULT NULL, object_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, impersonator_id VARCHAR(36) DEFAULT NULL, changes JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN change_log.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN change_log.date IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE change_log');
    }
}
