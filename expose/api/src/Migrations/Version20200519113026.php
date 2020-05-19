<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200519113026 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE publication ADD cover_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN publication.cover_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779922726E9 FOREIGN KEY (cover_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AF3C6779922726E9 ON publication (cover_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE publication DROP CONSTRAINT FK_AF3C6779922726E9');
        $this->addSql('DROP INDEX IDX_AF3C6779922726E9');
        $this->addSql('ALTER TABLE publication DROP cover_id');
    }
}
