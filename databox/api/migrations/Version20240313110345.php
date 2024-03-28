<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240313110345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE basket_basket_asset (basket_id UUID NOT NULL, basket_asset_id UUID NOT NULL, PRIMARY KEY(basket_id, basket_asset_id))');
        $this->addSql('CREATE INDEX IDX_1A9FEAA51BE1FB52 ON basket_basket_asset (basket_id)');
        $this->addSql('CREATE INDEX IDX_1A9FEAA561751691 ON basket_basket_asset (basket_asset_id)');
        $this->addSql('COMMENT ON COLUMN basket_basket_asset.basket_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN basket_basket_asset.basket_asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE basket_asset (id UUID NOT NULL, basket_id UUID NOT NULL, asset_id UUID NOT NULL, owner_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_54D711661BE1FB52 ON basket_asset (basket_id)');
        $this->addSql('CREATE INDEX IDX_54D711665DA1941 ON basket_asset (asset_id)');
        $this->addSql('COMMENT ON COLUMN basket_asset.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN basket_asset.basket_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN basket_asset.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN basket_asset.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE basket_basket_asset ADD CONSTRAINT FK_1A9FEAA51BE1FB52 FOREIGN KEY (basket_id) REFERENCES basket (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE basket_basket_asset ADD CONSTRAINT FK_1A9FEAA561751691 FOREIGN KEY (basket_asset_id) REFERENCES basket_asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE basket_asset ADD CONSTRAINT FK_54D711661BE1FB52 FOREIGN KEY (basket_id) REFERENCES basket (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE basket_asset ADD CONSTRAINT FK_54D711665DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE basket DROP CONSTRAINT fk_2246507b514956fd');
        $this->addSql('DROP INDEX idx_2246507b514956fd');
        $this->addSql('ALTER TABLE basket ADD title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE basket ADD owner_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE basket ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE basket ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE basket DROP collection_id');
        $this->addSql('COMMENT ON COLUMN basket.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN basket.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE basket_basket_asset DROP CONSTRAINT FK_1A9FEAA51BE1FB52');
        $this->addSql('ALTER TABLE basket_basket_asset DROP CONSTRAINT FK_1A9FEAA561751691');
        $this->addSql('ALTER TABLE basket_asset DROP CONSTRAINT FK_54D711661BE1FB52');
        $this->addSql('ALTER TABLE basket_asset DROP CONSTRAINT FK_54D711665DA1941');
        $this->addSql('DROP TABLE basket_basket_asset');
        $this->addSql('DROP TABLE basket_asset');
        $this->addSql('ALTER TABLE basket ADD collection_id UUID NOT NULL');
        $this->addSql('ALTER TABLE basket DROP title');
        $this->addSql('ALTER TABLE basket DROP owner_id');
        $this->addSql('ALTER TABLE basket DROP created_at');
        $this->addSql('ALTER TABLE basket DROP updated_at');
        $this->addSql('COMMENT ON COLUMN basket.collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE basket ADD CONSTRAINT fk_2246507b514956fd FOREIGN KEY (collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_2246507b514956fd ON basket (collection_id)');
    }
}
