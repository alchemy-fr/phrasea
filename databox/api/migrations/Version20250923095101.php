<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923095101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE asset_title_attribute ADD "target" SMALLINT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE asset_title_attribute SET "target" = 1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE asset_title_attribute ALTER COLUMN "target" SET NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE asset_title_attribute DROP "target"
        SQL);
    }
}
