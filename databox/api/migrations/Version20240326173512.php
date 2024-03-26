<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240326173512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE basket_basket_asset DROP CONSTRAINT fk_1a9feaa51be1fb52');
        $this->addSql('ALTER TABLE basket_basket_asset DROP CONSTRAINT fk_1a9feaa561751691');
        $this->addSql('DROP TABLE basket_basket_asset');
        $this->addSql('ALTER TABLE basket ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE basket_asset ADD position BIGINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE basket_basket_asset (basket_id UUID NOT NULL, basket_asset_id UUID NOT NULL, PRIMARY KEY(basket_id, basket_asset_id))');
        $this->addSql('CREATE INDEX idx_1a9feaa561751691 ON basket_basket_asset (basket_asset_id)');
        $this->addSql('CREATE INDEX idx_1a9feaa51be1fb52 ON basket_basket_asset (basket_id)');
        $this->addSql('COMMENT ON COLUMN basket_basket_asset.basket_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN basket_basket_asset.basket_asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE basket_basket_asset ADD CONSTRAINT fk_1a9feaa51be1fb52 FOREIGN KEY (basket_id) REFERENCES basket (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE basket_basket_asset ADD CONSTRAINT fk_1a9feaa561751691 FOREIGN KEY (basket_asset_id) REFERENCES basket_asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE basket_asset DROP position');
        $this->addSql('ALTER TABLE basket DROP description');
    }
}
