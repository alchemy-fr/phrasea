<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230124102058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_relationship DROP CONSTRAINT FK_5953845D158E0B66');
        $this->addSql('ALTER TABLE asset_relationship ADD source_file_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_relationship ADD integration_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_relationship ALTER source_id SET NOT NULL');
        $this->addSql('ALTER TABLE asset_relationship ALTER target_id SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN asset_relationship.source_file_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_relationship.integration_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset_relationship ADD CONSTRAINT FK_5953845DDA14C104 FOREIGN KEY (source_file_id) REFERENCES file (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_relationship ADD CONSTRAINT FK_5953845D9E82DDEA FOREIGN KEY (integration_id) REFERENCES workspace_integration (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset_relationship ADD CONSTRAINT FK_5953845D158E0B66 FOREIGN KEY (target_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_5953845DDA14C104 ON asset_relationship (source_file_id)');
        $this->addSql('CREATE INDEX IDX_5953845D9E82DDEA ON asset_relationship (integration_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_relationship DROP CONSTRAINT FK_5953845DDA14C104');
        $this->addSql('ALTER TABLE asset_relationship DROP CONSTRAINT FK_5953845D9E82DDEA');
        $this->addSql('ALTER TABLE asset_relationship DROP CONSTRAINT fk_5953845d158e0b66');
        $this->addSql('DROP INDEX IDX_5953845DDA14C104');
        $this->addSql('DROP INDEX IDX_5953845D9E82DDEA');
        $this->addSql('ALTER TABLE asset_relationship DROP source_file_id');
        $this->addSql('ALTER TABLE asset_relationship DROP integration_id');
        $this->addSql('ALTER TABLE asset_relationship ALTER source_id DROP NOT NULL');
        $this->addSql('ALTER TABLE asset_relationship ALTER target_id DROP NOT NULL');
        $this->addSql('ALTER TABLE asset_relationship ADD CONSTRAINT fk_5953845d158e0b66 FOREIGN KEY (target_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
