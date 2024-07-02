<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240702155025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_rendition ADD projection BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD asset_annotations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute DROP coordinates');
        $this->addSql('ALTER TABLE basket_asset ADD asset_annotations JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_rendition DROP projection');
        $this->addSql('ALTER TABLE attribute ADD coordinates TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute DROP asset_annotations');
        $this->addSql('ALTER TABLE basket_asset DROP asset_annotations');
    }
}
