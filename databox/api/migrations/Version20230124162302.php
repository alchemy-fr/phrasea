<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230124162302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_relationship DROP CONSTRAINT FK_5953845D953C1C61');
        $this->addSql('ALTER TABLE asset_relationship ADD CONSTRAINT FK_5953845D953C1C61 FOREIGN KEY (source_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_relationship DROP CONSTRAINT fk_5953845d953c1c61');
        $this->addSql('ALTER TABLE asset_relationship ADD CONSTRAINT fk_5953845d953c1c61 FOREIGN KEY (source_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
