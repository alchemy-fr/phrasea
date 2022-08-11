<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220802161731 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE target (id UUID NOT NULL, name VARCHAR(1000) NOT NULL, target_url VARCHAR(255) NOT NULL, target_access_token VARCHAR(2000) NOT NULL, allowed_groups JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN target.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset ADD target_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN asset.target_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C158E0B66 FOREIGN KEY (target_id) REFERENCES target (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_2AF5A5C158E0B66 ON asset (target_id)');
        $this->addSql('ALTER TABLE asset_commit ADD target_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN asset_commit.target_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset_commit ADD CONSTRAINT FK_A6F9A1B0158E0B66 FOREIGN KEY (target_id) REFERENCES target (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A6F9A1B0158E0B66 ON asset_commit (target_id)');
        $this->addSql('ALTER TABLE bulk_data ADD target_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN bulk_data.target_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bulk_data.data IS NULL');
        $this->addSql('ALTER TABLE bulk_data ADD CONSTRAINT FK_68FD8F15158E0B66 FOREIGN KEY (target_id) REFERENCES target (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_68FD8F15158E0B66 ON bulk_data (target_id)');
        $this->addSql('ALTER TABLE form_schema ADD target_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN form_schema.target_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE form_schema ADD CONSTRAINT FK_BA6095FC158E0B66 FOREIGN KEY (target_id) REFERENCES target (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_BA6095FC158E0B66 ON form_schema (target_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C158E0B66');
        $this->addSql('ALTER TABLE asset_commit DROP CONSTRAINT FK_A6F9A1B0158E0B66');
        $this->addSql('ALTER TABLE bulk_data DROP CONSTRAINT FK_68FD8F15158E0B66');
        $this->addSql('ALTER TABLE form_schema DROP CONSTRAINT FK_BA6095FC158E0B66');
        $this->addSql('DROP TABLE target');
        $this->addSql('DROP INDEX IDX_BA6095FC158E0B66');
        $this->addSql('ALTER TABLE form_schema DROP target_id');
        $this->addSql('DROP INDEX IDX_68FD8F15158E0B66');
        $this->addSql('ALTER TABLE bulk_data DROP target_id');
        $this->addSql('COMMENT ON COLUMN bulk_data.data IS \'(DC2Type:json_array)\'');
        $this->addSql('DROP INDEX IDX_A6F9A1B0158E0B66');
        $this->addSql('ALTER TABLE asset_commit DROP target_id');
        $this->addSql('DROP INDEX IDX_2AF5A5C158E0B66');
        $this->addSql('ALTER TABLE asset DROP target_id');
    }
}
