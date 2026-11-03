<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103181502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Asset imports (JSON/NDJSON instructions files): asset_import and asset_import_error';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE asset_import (id UUID NOT NULL, format VARCHAR(10) NOT NULL, privacy SMALLINT DEFAULT NULL, source_path VARCHAR(255) NOT NULL, original_name VARCHAR(255) DEFAULT NULL, status SMALLINT NOT NULL, total INT DEFAULT NULL, created INT DEFAULT 0 NOT NULL, skipped INT DEFAULT 0 NOT NULL, failed INT DEFAULT 0 NOT NULL, error TEXT DEFAULT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, owner_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, workspace_id UUID NOT NULL, collection_id UUID DEFAULT NULL, integration_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX asset_import_ws_created_idx ON asset_import (workspace_id, created_at)');
        $this->addSql('CREATE INDEX IDX_7563410082D40A1F ON asset_import (workspace_id)');
        $this->addSql('CREATE INDEX IDX_75634100514956FD ON asset_import (collection_id)');
        $this->addSql('CREATE INDEX IDX_756341009E82DDEA ON asset_import (integration_id)');
        $this->addSql('CREATE TABLE asset_import_error (id UUID NOT NULL, line INT NOT NULL, entry_key VARCHAR(255) DEFAULT NULL, message TEXT NOT NULL, import_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX asset_import_error_line_idx ON asset_import_error (import_id, line)');
        $this->addSql('CREATE INDEX IDX_C9DFD342B6A263D9 ON asset_import_error (import_id)');
        $this->addSql('ALTER TABLE asset_import ADD CONSTRAINT FK_7563410082D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE asset_import ADD CONSTRAINT FK_75634100514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE asset_import ADD CONSTRAINT FK_756341009E82DDEA FOREIGN KEY (integration_id) REFERENCES workspace_integration (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE asset_import_error ADD CONSTRAINT FK_C9DFD342B6A263D9 FOREIGN KEY (import_id) REFERENCES asset_import (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_import DROP CONSTRAINT FK_7563410082D40A1F');
        $this->addSql('ALTER TABLE asset_import DROP CONSTRAINT FK_75634100514956FD');
        $this->addSql('ALTER TABLE asset_import DROP CONSTRAINT FK_756341009E82DDEA');
        $this->addSql('ALTER TABLE asset_import_error DROP CONSTRAINT FK_C9DFD342B6A263D9');
        $this->addSql('DROP TABLE asset_import_error');
        $this->addSql('DROP TABLE asset_import');
    }
}
