<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221207170541 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE asset ADD publication_id UUID DEFAULT NULL');
        $this->addSql('UPDATE asset a SET publication_id = (SELECT pa.publication_id FROM publication_asset pa WHERE pa.asset_id = a.id LIMIT 1)');
        $this->addSql('ALTER TABLE asset ADD position SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE asset a SET position = (SELECT pa.position FROM publication_asset pa WHERE pa.asset_id = a.id LIMIT 1)');
        $this->addSql('DELETE FROM asset WHERE publication_id IS NULL');
        $this->addSql('ALTER TABLE asset ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN asset.publication_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C38B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_2AF5A5C38B217A7 ON asset (publication_id)');
        $this->addSql('DROP TABLE publication_asset');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE publication_asset (id UUID NOT NULL, publication_id UUID NOT NULL, asset_id UUID NOT NULL, slug VARCHAR(255) DEFAULT NULL, "position" SMALLINT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, client_annotations TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX unique_url ON publication_asset (publication_id, slug)');
        $this->addSql('CREATE INDEX idx_e7711cd038b217a7 ON publication_asset (publication_id)');
        $this->addSql('CREATE INDEX idx_e7711cd05da1941 ON publication_asset (asset_id)');
        $this->addSql('COMMENT ON COLUMN publication_asset.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN publication_asset.publication_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN publication_asset.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE publication_asset ADD CONSTRAINT fk_e7711cd038b217a7 FOREIGN KEY (publication_id) REFERENCES publication (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE publication_asset ADD CONSTRAINT fk_e7711cd05da1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE asset DROP CONSTRAINT FK_2AF5A5C38B217A7');
        $this->addSql('DROP INDEX IDX_2AF5A5C38B217A7');
        $this->addSql('ALTER TABLE asset DROP publication_id');
        $this->addSql('ALTER TABLE asset DROP slug');
        $this->addSql('ALTER TABLE asset DROP position');
    }
}
