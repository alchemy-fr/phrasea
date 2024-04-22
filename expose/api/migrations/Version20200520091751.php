<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200520091751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE download_request (id UUID NOT NULL, publication_id UUID DEFAULT NULL, asset_id UUID DEFAULT NULL, email VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_97E98E5A38B217A7 ON download_request (publication_id)');
        $this->addSql('CREATE INDEX IDX_97E98E5A5DA1941 ON download_request (asset_id)');
        $this->addSql('COMMENT ON COLUMN download_request.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN download_request.publication_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN download_request.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE download_request ADD CONSTRAINT FK_97E98E5A38B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE download_request ADD CONSTRAINT FK_97E98E5A5DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE download_request');
    }
}
