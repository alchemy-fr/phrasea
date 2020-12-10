<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201209130508 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE INDEX user_idx ON access_control_entry (user_type, user_id)');
        $this->addSql('CREATE INDEX object_idx ON access_control_entry (object_type, object_id)');
        $this->addSql('CREATE INDEX user_type_idx ON access_control_entry (user_type)');
        $this->addSql('CREATE INDEX object_type_idx ON access_control_entry (object_type)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX user_idx');
        $this->addSql('DROP INDEX object_idx');
        $this->addSql('DROP INDEX user_type_idx');
        $this->addSql('DROP INDEX object_type_idx');
    }
}
