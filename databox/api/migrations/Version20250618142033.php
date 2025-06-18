<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250618142033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition DROP CONSTRAINT fk_6c5628bdea000b10
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_6c5628bdea000b10
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition RENAME COLUMN class_id TO policy_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ADD CONSTRAINT FK_6C5628BD2D29E3C6 FOREIGN KEY (policy_id) REFERENCES attribute_policy (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6C5628BD2D29E3C6 ON attribute_definition (policy_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX uniq_class_ws_name RENAME TO uniq_policy_ws_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX uniq_class_ws_key RENAME TO uniq_policy_ws_key
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_definition DROP CONSTRAINT fk_63599969ea000b10
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_63599969ea000b10
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_definition RENAME COLUMN class_id TO policy_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_definition ADD CONSTRAINT FK_635999692D29E3C6 FOREIGN KEY (policy_id) REFERENCES rendition_policy (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_635999692D29E3C6 ON rendition_definition (policy_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX rend_class_uniq RENAME TO rend_policy_uniq
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX uniq_policy_ws_key RENAME TO uniq_class_ws_key
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX uniq_policy_ws_name RENAME TO uniq_class_ws_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX rend_policy_uniq RENAME TO rend_class_uniq
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition DROP CONSTRAINT FK_6C5628BD2D29E3C6
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_6C5628BD2D29E3C6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition RENAME COLUMN policy_id TO class_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_definition ADD CONSTRAINT fk_6c5628bdea000b10 FOREIGN KEY (class_id) REFERENCES attribute_policy (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_6c5628bdea000b10 ON attribute_definition (class_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_definition DROP CONSTRAINT FK_635999692D29E3C6
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_635999692D29E3C6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_definition RENAME COLUMN policy_id TO class_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendition_definition ADD CONSTRAINT fk_63599969ea000b10 FOREIGN KEY (class_id) REFERENCES rendition_policy (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_63599969ea000b10 ON rendition_definition (class_id)
        SQL);
    }
}
