<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250416165130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stories: bilateral OneToOne asset <-> collection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE asset ADD story_collection_id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN asset.story_collection_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE asset ADD CONSTRAINT FK_story_collection_id FOREIGN KEY (story_collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_story_collection_id ON asset (story_collection_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection ADD story_asset_id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN collection.story_asset_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection ADD CONSTRAINT FK_story_asset_id FOREIGN KEY (story_asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_story_asset_id ON collection (story_asset_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection DROP CONSTRAINT FK_story_asset_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_story_asset_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection DROP story_asset_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE asset DROP CONSTRAINT FK_story_collection_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_story_collection_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE asset DROP story_collection_id
        SQL);
    }
}
