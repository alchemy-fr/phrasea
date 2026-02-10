<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210123523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_attachment DROP CONSTRAINT fk_bfbe3ae193cb796c');
        $this->addSql('DROP INDEX idx_bfbe3ae193cb796c');
        $this->addSql('ALTER TABLE asset_attachment RENAME COLUMN file_id TO attachment_id');
        // Add new asset for each file that was attached to an asset, and update the attachment to point to the new asset:
        $this->addSql('INSERT INTO asset (id, workspace_id, source_id, created_at, updated_at, attributes_edited_at, tags_edited_at, microseconds, sequence, locale, owner_id, privacy, notification_settings) SELECT gen_random_uuid(), f.workspace_id, f.id, f.created_at, f.updated_at, f.updated_at, f.updated_at, 0, 0, a.locale, a.owner_id, a.privacy, a.notification_settings FROM file f INNER JOIN asset_attachment aa ON aa.attachment_id = f.id INNER JOIN asset a ON a.id = aa.asset_id');
        $this->addSql('UPDATE asset_attachment aa SET attachment_id = (SELECT a.id FROM asset a WHERE a.source_id = aa.attachment_id)');

        $this->addSql('ALTER TABLE asset_attachment ADD CONSTRAINT FK_BFBE3AE1464E68B FOREIGN KEY (attachment_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_BFBE3AE1464E68B ON asset_attachment (attachment_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_attachment DROP CONSTRAINT FK_BFBE3AE1464E68B');
        $this->addSql('DROP INDEX IDX_BFBE3AE1464E68B');
        $this->addSql('ALTER TABLE asset_attachment RENAME COLUMN attachment_id TO file_id');
        $this->addSql('ALTER TABLE asset_attachment ADD CONSTRAINT fk_bfbe3ae193cb796c FOREIGN KEY (file_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_bfbe3ae193cb796c ON asset_attachment (file_id)');
    }
}
