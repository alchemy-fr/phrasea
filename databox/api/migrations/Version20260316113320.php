<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316113320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_control_entry ALTER created_at TYPE DATE');
        $this->addSql('COMMENT ON COLUMN access_control_entry.created_at IS \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE workspace_integration ALTER public DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE workspace_integration ALTER public SET DEFAULT true');
        $this->addSql('ALTER TABLE access_control_entry ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN access_control_entry.created_at IS NULL');
    }
}
