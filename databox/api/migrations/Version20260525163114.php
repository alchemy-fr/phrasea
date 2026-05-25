<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525163114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add collection access table to manage user permissions on collections';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE collection_access (id SERIAL NOT NULL, collection_id UUID NOT NULL, workspace_id UUID NOT NULL, user_id UUID DEFAULT NULL, privacy SMALLINT DEFAULT NULL, path LTREE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F44CD3A2514956FD ON collection_access (collection_id)');
        $this->addSql('CREATE INDEX IDX_F44CD3A282D40A1F ON collection_access (workspace_id)');
        $this->addSql('CREATE INDEX ca_path_idx ON collection_access (path)');
        $this->addSql('CREATE INDEX ca_privacy_idx ON collection_access (privacy)');
        $this->addSql('CREATE INDEX ca_user_privacy_idx ON collection_access (user_id, privacy)');
        $this->addSql('CREATE UNIQUE INDEX collection_access_uniq ON collection_access (collection_id, user_id)');
        $this->addSql('COMMENT ON COLUMN collection_access.collection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN collection_access.workspace_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN collection_access.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE collection_access ADD CONSTRAINT FK_F44CD3A2514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE collection_access ADD CONSTRAINT FK_F44CD3A282D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE INDEX gist_path_idx ON collection_access USING GIST (path)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_access DROP CONSTRAINT FK_F44CD3A2514956FD');
        $this->addSql('ALTER TABLE collection_access DROP CONSTRAINT FK_F44CD3A282D40A1F');
        $this->addSql('DROP TABLE collection_access');
    }
}
