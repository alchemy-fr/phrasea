<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220707101608 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE external_access_token ALTER access_token TYPE VARCHAR(5000)');
        $this->addSql('ALTER TABLE external_access_token ALTER refresh_token TYPE VARCHAR(5000)');
        $this->addSql('COMMENT ON COLUMN "group".roles IS NULL');
        $this->addSql('COMMENT ON COLUMN saml_identity.attributes IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".roles IS NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE external_access_token ALTER access_token TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE external_access_token ALTER refresh_token TYPE VARCHAR(255)');
        $this->addSql('COMMENT ON COLUMN saml_identity.attributes IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN "user".roles IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN "group".roles IS \'(DC2Type:json_array)\'');
    }
}
