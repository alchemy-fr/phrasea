<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250409113833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ADD translations JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ALTER enabled SET DEFAULT true
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection ADD translations JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_definition ADD translations JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workspace ADD translations JSON DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection DROP translations
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_definition DROP translations
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition DROP translations
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ALTER enabled DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workspace DROP translations
        SQL);
    }
}
