<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Multi-asset shares (share_asset join table) and workspace Terms & Conditions (terms_version, terms_signature)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE share_asset (share_id UUID NOT NULL, asset_id UUID NOT NULL, PRIMARY KEY(share_id, asset_id))');
        $this->addSql('CREATE INDEX IDX_14C4E1A32AE63FDB ON share_asset (share_id)');
        $this->addSql('CREATE INDEX IDX_14C4E1A35DA1941 ON share_asset (asset_id)');
        $this->addSql('COMMENT ON COLUMN share_asset.share_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN share_asset.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE share_asset ADD CONSTRAINT FK_14C4E1A32AE63FDB FOREIGN KEY (share_id) REFERENCES share (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE share_asset ADD CONSTRAINT FK_14C4E1A35DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO share_asset (share_id, asset_id) SELECT id, asset_id FROM share');
        $this->addSql('ALTER TABLE share DROP CONSTRAINT fk_ef069d5a5da1941');
        $this->addSql('DROP INDEX idx_ef069d5a5da1941');
        $this->addSql('ALTER TABLE share DROP asset_id');

        $this->addSql('CREATE TABLE terms_version (id UUID NOT NULL, workspace_id UUID NOT NULL, version INT NOT NULL, text TEXT NOT NULL, pdf_path VARCHAR(255) DEFAULT NULL, pdf_checksum VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_620E039182D40A1F ON terms_version (workspace_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_terms_version ON terms_version (workspace_id, version)');
        $this->addSql('COMMENT ON COLUMN terms_version.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN terms_version.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN terms_version.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE terms_version ADD CONSTRAINT FK_620E039182D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE terms_signature (id UUID NOT NULL, terms_version_id UUID NOT NULL, user_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A126F19CE29B7C7C ON terms_signature (terms_version_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_terms_signature ON terms_signature (terms_version_id, user_id)');
        $this->addSql('COMMENT ON COLUMN terms_signature.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN terms_signature.terms_version_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN terms_signature.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE terms_signature ADD CONSTRAINT FK_A126F19CE29B7C7C FOREIGN KEY (terms_version_id) REFERENCES terms_version (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE share ADD asset_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN share.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('UPDATE share s SET asset_id = (SELECT sa.asset_id FROM share_asset sa WHERE sa.share_id = s.id LIMIT 1)');
        $this->addSql('DELETE FROM share WHERE asset_id IS NULL');
        $this->addSql('ALTER TABLE share ALTER asset_id SET NOT NULL');
        $this->addSql('CREATE INDEX idx_ef069d5a5da1941 ON share (asset_id)');
        $this->addSql('ALTER TABLE share ADD CONSTRAINT fk_ef069d5a5da1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE share_asset');

        $this->addSql('DROP TABLE terms_signature');
        $this->addSql('DROP TABLE terms_version');
    }
}
