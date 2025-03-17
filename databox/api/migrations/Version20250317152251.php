<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250317152251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stories: bilateral OneToOne asset <-> collection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT fk_2af5a5c717dfdc8');
        $this->addSql('DROP INDEX idx_2af5a5c717dfdc8');
        $this->addSql('ALTER TABLE asset DROP story_collection_id');
        $this->addSql('ALTER TABLE collection ADD CONSTRAINT FK_FC4D653263F07DD FOREIGN KEY (story_asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FC4D653263F07DD ON collection (story_asset_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset ADD story_collection_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN asset.story_collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT fk_2af5a5c717dfdc8 FOREIGN KEY (story_collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_2af5a5c717dfdc8 ON asset (story_collection_id)');
        $this->addSql('ALTER TABLE collection DROP CONSTRAINT FK_FC4D653263F07DD');
        $this->addSql('DROP INDEX UNIQ_FC4D653263F07DD');
    }
}
