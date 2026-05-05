<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427171812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profile_data DROP CONSTRAINT fk_1b3b7cf7ccfa12b8');
        $this->addSql('DROP INDEX uniq_1b3b7cf7ccfa12b8');
        $this->addSql('ALTER TABLE profile_data DROP profile_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE profile_data ADD profile_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN profile_data.profile_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE profile_data ADD CONSTRAINT fk_1b3b7cf7ccfa12b8 FOREIGN KEY (profile_id) REFERENCES profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_1b3b7cf7ccfa12b8 ON profile_data (profile_id)');
    }
}
