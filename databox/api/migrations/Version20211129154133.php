<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211129154133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset ALTER locale TYPE VARCHAR(10)');
        $this->addSql('ALTER TABLE attribute ALTER locale TYPE VARCHAR(10)');
        $this->addSql('ALTER TABLE collection ALTER locale TYPE VARCHAR(10)');
        $this->addSql('ALTER TABLE tag ALTER locale TYPE VARCHAR(10)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE tag ALTER locale TYPE VARCHAR(2)');
        $this->addSql('ALTER TABLE asset ALTER locale TYPE VARCHAR(2)');
        $this->addSql('ALTER TABLE attribute ALTER locale TYPE VARCHAR(2)');
        $this->addSql('ALTER TABLE collection ALTER locale TYPE VARCHAR(2)');
    }
}
