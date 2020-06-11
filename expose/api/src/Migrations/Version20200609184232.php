<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200609184232 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE publication ADD config_map_options JSON NULL');
        $this->addSql('UPDATE publication SET config_map_options = \'[]\'');
        $this->addSql('ALTER TABLE publication ALTER COLUMN config_map_options SET NOT NULL');

        $this->addSql('COMMENT ON COLUMN publication.config_security_options IS NULL');

        $this->addSql('ALTER TABLE publication_profile ADD config_map_options JSON NULL');
        $this->addSql('UPDATE publication_profile SET config_map_options = \'[]\'');
        $this->addSql('ALTER TABLE publication_profile ALTER COLUMN config_map_options SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN publication_profile.config_security_options IS NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE publication DROP config_map_options');
        $this->addSql('COMMENT ON COLUMN publication.config_security_options IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE publication_profile DROP config_map_options');
        $this->addSql('COMMENT ON COLUMN publication_profile.config_security_options IS \'(DC2Type:json_array)\'');
    }
}
