<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241210114108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_commit ADD notify BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('UPDATE asset_commit SET notify = true WHERE notify_email IS NOT NULL');
        $this->addSql('ALTER TABLE asset_commit DROP notify_email');
        $this->addSql('ALTER TABLE asset_commit ALTER notify DROP DEFAULT');
        $this->addSql('ALTER TABLE target ALTER hidden DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE target ALTER hidden SET DEFAULT false');
        $this->addSql('ALTER TABLE asset_commit ADD notify_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_commit DROP notify');
    }
}
