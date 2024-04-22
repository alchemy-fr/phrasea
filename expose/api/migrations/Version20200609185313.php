<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200609185313 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE publication ADD config_layout_options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ALTER config_map_options DROP NOT NULL');
        $this->addSql('ALTER TABLE publication_profile ADD config_layout_options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE publication_profile ALTER config_map_options DROP NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE publication DROP config_layout_options');
        $this->addSql('ALTER TABLE publication ALTER config_map_options SET NOT NULL');
        $this->addSql('ALTER TABLE publication_profile DROP config_layout_options');
        $this->addSql('ALTER TABLE publication_profile ALTER config_map_options SET NOT NULL');
    }
}
