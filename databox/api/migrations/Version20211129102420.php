<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211129102420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_definition ADD public BOOLEAN DEFAULT NULL');
        $this->addSql('CREATE INDEX public_searchable_idx ON attribute_definition (searchable, public)');
        $this->addSql('CREATE INDEX searchable_idx ON attribute_definition (searchable)');
        $this->addSql('CREATE INDEX public_idx ON attribute_definition (public)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX public_searchable_idx');
        $this->addSql('DROP INDEX searchable_idx');
        $this->addSql('DROP INDEX public_idx');
        $this->addSql('ALTER TABLE attribute_definition DROP public');
    }
}
