<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250429162120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stories: bilateral OneToOne asset <-> collection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX idx_2af5a5c717dfdc8
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_2AF5A5C717DFDC8 ON asset (story_collection_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection ADD story_asset_id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN collection.story_asset_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection ADD CONSTRAINT FK_FC4D653263F07DD FOREIGN KEY (story_asset_id) REFERENCES asset (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_FC4D653263F07DD ON collection (story_asset_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE collection DROP CONSTRAINT FK_FC4D653263F07DD
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_FC4D653263F07DD
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection DROP story_asset_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_2AF5A5C717DFDC8
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_2af5a5c717dfdc8 ON asset (story_collection_id)
        SQL);
    }
}
