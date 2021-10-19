<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211019154052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sub_definition_spec (id UUID NOT NULL, workspace_id UUID NOT NULL, name VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4F0B98C582D40A1F ON sub_definition_spec (workspace_id)');
        $this->addSql('CREATE INDEX ws_name ON sub_definition_spec (workspace_id, name)');
        $this->addSql('ALTER TABLE sub_definition_spec ADD CONSTRAINT FK_4F0B98C582D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE metadata_value DROP CONSTRAINT FK_FF14E0565DA1941');
        $this->addSql('ALTER TABLE metadata_value ADD CONSTRAINT FK_FF14E0565DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE multipart_upload ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE multipart_upload ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition DROP CONSTRAINT fk_44fbbc1582d40a1f');
        $this->addSql('DROP INDEX idx_44fbbc1582d40a1f');
        $this->addSql('ALTER TABLE sub_definition ADD specification_id UUID NOT NULL');
        $this->addSql('ALTER TABLE sub_definition ADD asset_id UUID NOT NULL');
        $this->addSql('ALTER TABLE sub_definition ADD file_id UUID NOT NULL');
        $this->addSql('ALTER TABLE sub_definition DROP workspace_id');
        $this->addSql('ALTER TABLE sub_definition DROP type');
        $this->addSql('ALTER TABLE sub_definition DROP size');
        $this->addSql('ALTER TABLE sub_definition DROP checksum');
        $this->addSql('ALTER TABLE sub_definition DROP path');
        $this->addSql('ALTER TABLE sub_definition ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ADD CONSTRAINT FK_44FBBC15908E2FFE FOREIGN KEY (specification_id) REFERENCES sub_definition_spec (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition ADD CONSTRAINT FK_44FBBC155DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_definition ADD CONSTRAINT FK_44FBBC1593CB796C FOREIGN KEY (file_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_44FBBC15908E2FFE ON sub_definition (specification_id)');
        $this->addSql('CREATE INDEX IDX_44FBBC155DA1941 ON sub_definition (asset_id)');
        $this->addSql('CREATE INDEX IDX_44FBBC1593CB796C ON sub_definition (file_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE sub_definition DROP CONSTRAINT FK_44FBBC15908E2FFE');
        $this->addSql('DROP TABLE sub_definition_spec');
        $this->addSql('ALTER TABLE metadata_value DROP CONSTRAINT fk_ff14e0565da1941');
        $this->addSql('ALTER TABLE metadata_value ADD CONSTRAINT fk_ff14e0565da1941 FOREIGN KEY (asset_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE multipart_upload ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE multipart_upload ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition DROP CONSTRAINT FK_44FBBC155DA1941');
        $this->addSql('ALTER TABLE sub_definition DROP CONSTRAINT FK_44FBBC1593CB796C');
        $this->addSql('DROP INDEX IDX_44FBBC15908E2FFE');
        $this->addSql('DROP INDEX IDX_44FBBC155DA1941');
        $this->addSql('DROP INDEX IDX_44FBBC1593CB796C');
        $this->addSql('ALTER TABLE sub_definition ADD workspace_id UUID NOT NULL');
        $this->addSql('ALTER TABLE sub_definition ADD type VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_definition ADD size INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_definition ADD checksum VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_definition ADD path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_definition DROP specification_id');
        $this->addSql('ALTER TABLE sub_definition DROP asset_id');
        $this->addSql('ALTER TABLE sub_definition DROP file_id');
        $this->addSql('ALTER TABLE sub_definition ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE sub_definition ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_definition ADD CONSTRAINT fk_44fbbc1582d40a1f FOREIGN KEY (workspace_id) REFERENCES workspace (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_44fbbc1582d40a1f ON sub_definition (workspace_id)');
    }
}
