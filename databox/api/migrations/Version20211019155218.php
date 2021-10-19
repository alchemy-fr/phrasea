<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211019155218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT fk_2af5a5ccde46fdb');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT fk_2af5a5cc7034ea5');
        $this->addSql('DROP INDEX idx_2af5a5cc7034ea5');
        $this->addSql('DROP INDEX idx_2af5a5ccde46fdb');
        $this->addSql('ALTER TABLE asset DROP preview_id');
        $this->addSql('ALTER TABLE asset DROP thumb_id');
        $this->addSql('ALTER TABLE multipart_upload ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE multipart_upload ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ADD ready BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE sub_definition ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ALTER specification_id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER specification_id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ALTER asset_id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER asset_id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ALTER file_id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER file_id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition_spec ADD use_as_preview BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE sub_definition_spec ADD use_as_thumbnail BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE sub_definition_spec ADD use_as_thumbnail_active BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE sub_definition_spec ADD definition TEXT NOT NULL');
        $this->addSql('ALTER TABLE sub_definition_spec ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition_spec ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition_spec ALTER workspace_id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition_spec ALTER workspace_id DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset ADD preview_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE asset ADD thumb_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT fk_2af5a5ccde46fdb FOREIGN KEY (preview_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT fk_2af5a5cc7034ea5 FOREIGN KEY (thumb_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_2af5a5cc7034ea5 ON asset (thumb_id)');
        $this->addSql('CREATE INDEX idx_2af5a5ccde46fdb ON asset (preview_id)');
        $this->addSql('ALTER TABLE multipart_upload ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE multipart_upload ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition_spec DROP use_as_preview');
        $this->addSql('ALTER TABLE sub_definition_spec DROP use_as_thumbnail');
        $this->addSql('ALTER TABLE sub_definition_spec DROP use_as_thumbnail_active');
        $this->addSql('ALTER TABLE sub_definition_spec DROP definition');
        $this->addSql('ALTER TABLE sub_definition_spec ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition_spec ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition_spec ALTER workspace_id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition_spec ALTER workspace_id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition DROP ready');
        $this->addSql('ALTER TABLE sub_definition ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ALTER specification_id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER specification_id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ALTER asset_id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER asset_id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ALTER file_id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER file_id DROP DEFAULT');
    }
}
