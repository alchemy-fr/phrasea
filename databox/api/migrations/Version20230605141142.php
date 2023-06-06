<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230605141142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset ADD tags_edited_at TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN asset.tags_edited_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE asset SET tags_edited_at = updated_at');
        $this->addSql('ALTER TABLE asset ALTER COLUMN tags_edited_at SET NOT NULL;');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset DROP tags_edited_at');
    }
}
