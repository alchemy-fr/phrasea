<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240716165827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_item ADD workspace_id UUID NOT NULL');
        $this->addSql('COMMENT ON COLUMN attribute_item.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE attribute_item ADD CONSTRAINT FK_44F3819682D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_44F3819682D40A1F ON attribute_item (workspace_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE attribute_item DROP CONSTRAINT FK_44F3819682D40A1F');
        $this->addSql('DROP INDEX IDX_44F3819682D40A1F');
        $this->addSql('ALTER TABLE attribute_item DROP workspace_id');
    }
}
