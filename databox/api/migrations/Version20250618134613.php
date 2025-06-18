<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250618134613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_e73fa2982d40a1f RENAME TO IDX_D29C2B4C82D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_8e3e63a882d40a1f RENAME TO IDX_48A3D56382D40A1F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sdr_allowed DROP CONSTRAINT fk_e8839a323e9beca9
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_e8839a323e9beca9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sdr_allowed DROP CONSTRAINT sdr_allowed_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sdr_allowed RENAME COLUMN rendition_class_id TO rendition_policy_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sdr_allowed ADD CONSTRAINT FK_E8839A329F407309 FOREIGN KEY (rendition_policy_id) REFERENCES rendition_policy (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E8839A329F407309 ON sdr_allowed (rendition_policy_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sdr_allowed ADD PRIMARY KEY (rendition_rule_id, rendition_policy_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_d29c2b4c82d40a1f RENAME TO idx_e73fa2982d40a1f
        SQL);
        $this->addSql(<<<'SQL'
            ALTER INDEX idx_48a3d56382d40a1f RENAME TO idx_8e3e63a882d40a1f
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sdr_allowed DROP CONSTRAINT FK_E8839A329F407309
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_E8839A329F407309
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX sdr_allowed_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sdr_allowed RENAME COLUMN rendition_policy_id TO rendition_class_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sdr_allowed ADD CONSTRAINT fk_e8839a323e9beca9 FOREIGN KEY (rendition_class_id) REFERENCES rendition_policy (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_e8839a323e9beca9 ON sdr_allowed (rendition_class_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sdr_allowed ADD PRIMARY KEY (rendition_rule_id, rendition_class_id)
        SQL);
    }
}
