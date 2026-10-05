<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a position to collection assets, so that a collection or a story can be ordered';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_asset ADD position INT DEFAULT NULL');
        // Seed the ranks from the current implicit order, so nothing visibly moves
        $this->addSql(<<<'SQL'
            UPDATE collection_asset SET position = s.rn
            FROM (
                SELECT id, (ROW_NUMBER() OVER (
                    PARTITION BY collection_id ORDER BY created_at ASC, id ASC
                ) - 1) AS rn
                FROM collection_asset
            ) s
            WHERE s.id = collection_asset.id
            SQL);
        $this->addSql('ALTER TABLE collection_asset ALTER "position" SET NOT NULL');
        $this->addSql('CREATE INDEX idx_coll_asset_position ON collection_asset (collection_id, position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_coll_asset_position');
        $this->addSql('ALTER TABLE collection_asset DROP position');
    }
}
