<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250505193414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX list_def_uniq
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_list_definition ADD built_in VARCHAR(30) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_list_definition ALTER definition_id DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX list_def_uniq ON attribute_list_definition (list_id, definition_id, built_in)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX list_def_uniq
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_list_definition DROP built_in
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_list_definition ALTER definition_id SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX list_def_uniq ON attribute_list_definition (list_id, definition_id)
        SQL);
    }
}
