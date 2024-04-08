<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240327112016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE basket_asset DROP CONSTRAINT FK_54D711661BE1FB52');
        $this->addSql('ALTER TABLE basket_asset DROP CONSTRAINT FK_54D711665DA1941');
        $this->addSql('ALTER TABLE basket_asset ALTER "position" DROP DEFAULT');
        $this->addSql('ALTER TABLE basket_asset ADD CONSTRAINT FK_54D711661BE1FB52 FOREIGN KEY (basket_id) REFERENCES basket (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE basket_asset ADD CONSTRAINT FK_54D711665DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE basket_asset DROP CONSTRAINT fk_54d711661be1fb52');
        $this->addSql('ALTER TABLE basket_asset DROP CONSTRAINT fk_54d711665da1941');
        $this->addSql('ALTER TABLE basket_asset ALTER position SET DEFAULT 0');
        $this->addSql('ALTER TABLE basket_asset ADD CONSTRAINT fk_54d711661be1fb52 FOREIGN KEY (basket_id) REFERENCES basket (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE basket_asset ADD CONSTRAINT fk_54d711665da1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
