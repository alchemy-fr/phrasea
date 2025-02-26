<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250224120747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset ADD extra_metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE collection ADD extra_metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE collection_asset ADD extra_metadata JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE collection DROP extra_metadata');
        $this->addSql('ALTER TABLE asset DROP extra_metadata');
        $this->addSql('ALTER TABLE collection_asset DROP extra_metadata');
    }
}
