<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250423183712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stories: bilateral OneToOne asset <-> collection';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE asset ADD story_collection_id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN asset.story_collection_id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C717DFDC8 FOREIGN KEY (story_collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE
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
            ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C717DFDC8
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_2AF5A5C717DFDC8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE asset DROP story_collection_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection DROP CONSTRAINT FK_FC4D653263F07DD
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_FC4D653263F07DD
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE collection DROP story_asset_id
        SQL);
    }
}
