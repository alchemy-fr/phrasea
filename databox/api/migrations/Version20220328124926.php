<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220328124926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE workspace ADD locale_fallbacks JSON DEFAULT NULL');
        $this->addSql('UPDATE workspace SET locale_fallbacks = \'["en"]\'');
        $this->addSql('ALTER TABLE workspace DROP locale_fallback');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE workspace ADD locale_fallback VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE workspace DROP locale_fallbacks');
    }
}
