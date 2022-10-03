<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220919135109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE integration_data DROP CONSTRAINT fk_986dce785da1941');
        $this->addSql('DROP INDEX idx_986dce785da1941');
        $this->addSql('DROP INDEX name');
        $this->addSql('ALTER TABLE integration_data RENAME COLUMN asset_id TO file_id');
        $this->addSql('UPDATE integration_data d SET file_id = (SELECT a.file_id FROM asset a WHERE a.id = d.file_id)');
        $this->addSql('UPDATE integration_data SET name = \'labels\' WHERE name = \'image_labels\'');
        $this->addSql('ALTER TABLE integration_data ADD CONSTRAINT FK_986DCE7893CB796C FOREIGN KEY (file_id) REFERENCES file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_986DCE7893CB796C ON integration_data (file_id)');
        $this->addSql('CREATE INDEX name ON integration_data (integration_id, file_id, name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE integration_data DROP CONSTRAINT FK_986DCE7893CB796C');
        $this->addSql('DROP INDEX IDX_986DCE7893CB796C');
        $this->addSql('DROP INDEX name');
        $this->addSql('ALTER TABLE integration_data RENAME COLUMN file_id TO asset_id');
        $this->addSql('ALTER TABLE integration_data ADD CONSTRAINT fk_986dce785da1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_986dce785da1941 ON integration_data (asset_id)');
        $this->addSql('CREATE INDEX name ON integration_data (integration_id, asset_id, name)');
    }
}
