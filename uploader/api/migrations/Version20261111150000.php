<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Upload links: anonymous uploads to a target, within limits';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE upload_link (id UUID NOT NULL, name VARCHAR(255) DEFAULT NULL, token VARCHAR(64) NOT NULL, enabled BOOLEAN NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, max_assets INT DEFAULT NULL, max_total_size BIGINT DEFAULT NULL, used_assets INT NOT NULL, used_size BIGINT NOT NULL, owner_id VARCHAR(255) NOT NULL, last_form_data JSON DEFAULT NULL, last_commit_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, target_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B2281E885F37A13B ON upload_link (token)');
        $this->addSql('CREATE INDEX IDX_B2281E88158E0B66 ON upload_link (target_id)');
        $this->addSql('ALTER TABLE upload_link ADD CONSTRAINT FK_B2281E88158E0B66 FOREIGN KEY (target_id) REFERENCES target (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('ALTER TABLE asset ADD upload_link_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C6893189F FOREIGN KEY (upload_link_id) REFERENCES upload_link (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_2AF5A5C6893189F ON asset (upload_link_id)');

        $this->addSql('ALTER TABLE asset_commit ADD upload_link_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_commit ADD CONSTRAINT FK_A6F9A1B06893189F FOREIGN KEY (upload_link_id) REFERENCES upload_link (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A6F9A1B06893189F ON asset_commit (upload_link_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_commit DROP CONSTRAINT FK_A6F9A1B06893189F');
        $this->addSql('DROP INDEX IDX_A6F9A1B06893189F');
        $this->addSql('ALTER TABLE asset_commit DROP upload_link_id');

        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C6893189F');
        $this->addSql('DROP INDEX IDX_2AF5A5C6893189F');
        $this->addSql('ALTER TABLE asset DROP upload_link_id');

        $this->addSql('ALTER TABLE upload_link DROP CONSTRAINT FK_B2281E88158E0B66');
        $this->addSql('DROP TABLE upload_link');
    }
}
