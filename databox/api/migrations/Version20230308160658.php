<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230308160658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_data_template ADD privacy SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE template_attribute DROP CONSTRAINT FK_3329994D5DA0FB8');
        $this->addSql('ALTER TABLE template_attribute ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE template_attribute ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE template_attribute ADD CONSTRAINT FK_3329994D5DA0FB8 FOREIGN KEY (template_id) REFERENCES asset_data_template (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_data_template DROP privacy');
        $this->addSql('ALTER TABLE template_attribute DROP CONSTRAINT fk_3329994d5da0fb8');
        $this->addSql('ALTER TABLE template_attribute DROP updated_at');
        $this->addSql('ALTER TABLE template_attribute DROP created_at');
        $this->addSql('ALTER TABLE template_attribute ADD CONSTRAINT fk_3329994d5da0fb8 FOREIGN KEY (template_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
