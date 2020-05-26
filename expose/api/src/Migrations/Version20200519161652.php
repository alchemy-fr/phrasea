<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200519161652 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE publication ADD config_download_terms_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD config_download_terms_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE publication_profile ADD config_download_terms_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication_profile ADD config_download_terms_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE publication_profile DROP config_download_terms_text');
        $this->addSql('ALTER TABLE publication_profile DROP config_download_terms_url');
        $this->addSql('ALTER TABLE publication DROP config_download_terms_text');
        $this->addSql('ALTER TABLE publication DROP config_download_terms_url');
    }
}
