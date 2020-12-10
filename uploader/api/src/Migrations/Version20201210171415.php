<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201210171415 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE access_control_entry ALTER object_id DROP DEFAULT');
        $this->addSql('ALTER TABLE access_control_entry ALTER object_id TYPE UUID USING uuid(object_id)');
        $this->addSql('COMMENT ON COLUMN access_control_entry.object_id IS \'(DC2Type:uuid)\'');
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
        $this->addSql('ALTER TABLE access_control_entry ALTER object_id TYPE VARCHAR(36)');
        $this->addSql('ALTER TABLE access_control_entry ALTER object_id DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN access_control_entry.object_id IS NULL');
    }
}
