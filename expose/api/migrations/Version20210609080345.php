<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210609080345 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE download_request ADD sub_definition_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN download_request.sub_definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE download_request ADD CONSTRAINT FK_97E98E5A1EB36ECF FOREIGN KEY (sub_definition_id) REFERENCES sub_definition (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_97E98E5A1EB36ECF ON download_request (sub_definition_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE download_request DROP CONSTRAINT FK_97E98E5A1EB36ECF');
        $this->addSql('DROP INDEX IDX_97E98E5A1EB36ECF');
        $this->addSql('ALTER TABLE download_request DROP sub_definition_id');
    }
}
