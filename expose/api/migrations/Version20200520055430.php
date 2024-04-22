<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200520055430 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE publication ADD config_download_via_email BOOLEAN NULL');
        $this->addSql('UPDATE publication SET config_download_via_email = false');
        $this->addSql('ALTER TABLE publication ALTER COLUMN config_download_via_email SET NOT NULL');
        $this->addSql('ALTER TABLE publication_profile ADD config_download_via_email BOOLEAN NULL');
        $this->addSql('UPDATE publication_profile SET config_download_via_email = false');
        $this->addSql('ALTER TABLE publication_profile ALTER COLUMN config_download_via_email SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');
    }
}
