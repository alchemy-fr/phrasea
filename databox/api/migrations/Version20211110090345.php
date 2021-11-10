<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211110090345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute ADD origin SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE attribute ADD origin_vendor VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD origin_user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD origin_vendor_context TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD coordinates TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD status SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD confidence DOUBLE PRECISION NOT NULL');
        $this->addSql('COMMENT ON COLUMN attribute.origin_user_id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE attribute DROP origin');
        $this->addSql('ALTER TABLE attribute DROP origin_vendor');
        $this->addSql('ALTER TABLE attribute DROP origin_user_id');
        $this->addSql('ALTER TABLE attribute DROP origin_vendor_context');
        $this->addSql('ALTER TABLE attribute DROP coordinates');
        $this->addSql('ALTER TABLE attribute DROP status');
        $this->addSql('ALTER TABLE attribute DROP confidence');
    }
}
