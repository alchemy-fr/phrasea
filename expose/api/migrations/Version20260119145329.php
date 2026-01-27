<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260119145329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove config_urls and config_copyright_text from publication and publication_profile tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE publication DROP config_urls');
        $this->addSql('ALTER TABLE publication DROP config_copyright_text');
        $this->addSql('ALTER TABLE publication_profile DROP config_urls');
        $this->addSql('ALTER TABLE publication_profile DROP config_copyright_text');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE publication ADD config_urls JSON NOT NULL');
        $this->addSql('ALTER TABLE publication ADD config_copyright_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication_profile ADD config_urls JSON NOT NULL');
        $this->addSql('ALTER TABLE publication_profile ADD config_copyright_text TEXT DEFAULT NULL');
    }
}
