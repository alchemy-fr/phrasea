<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240828200522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rendition_definition ADD parent_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE rendition_definition ALTER substitutable DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN rendition_definition.parent_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE rendition_definition ADD CONSTRAINT FK_63599969727ACA70 FOREIGN KEY (parent_id) REFERENCES rendition_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_63599969727ACA70 ON rendition_definition (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE rendition_definition DROP CONSTRAINT FK_63599969727ACA70');
        $this->addSql('DROP INDEX IDX_63599969727ACA70');
        $this->addSql('ALTER TABLE rendition_definition DROP parent_id');
        $this->addSql('ALTER TABLE rendition_definition ALTER substitutable SET DEFAULT true');
    }
}
