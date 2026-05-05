<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415134103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renames attribute lists to profiles and adds a unique constraint to profile items';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE profile (id UUID NOT NULL, title VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, public BOOLEAN NOT NULL, owner_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN profile.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN profile.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN profile.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE profile_item (id UUID NOT NULL, profile_id UUID NOT NULL, definition_id UUID DEFAULT NULL, type SMALLINT NOT NULL, key VARCHAR(150) DEFAULT NULL, options JSON DEFAULT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A9D3AA8ACCFA12B8 ON profile_item (profile_id)');
        $this->addSql('CREATE INDEX IDX_A9D3AA8AD11EA911 ON profile_item (definition_id)');
        $this->addSql('CREATE UNIQUE INDEX profile_def_uniq ON profile_item (profile_id, definition_id, key, type)');
        $this->addSql('COMMENT ON COLUMN profile_item.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN profile_item.profile_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN profile_item.definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE profile_item ADD CONSTRAINT FK_A9D3AA8ACCFA12B8 FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE profile_item ADD CONSTRAINT FK_A9D3AA8AD11EA911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute_list_item DROP CONSTRAINT fk_e7f48a713dae168b');
        $this->addSql('ALTER TABLE attribute_list_item DROP CONSTRAINT fk_e7f48a71d11ea911');

        $this->addSql('INSERT INTO profile (id, title, description, public, owner_id, created_at, updated_at) SELECT id, title, description, public, owner_id, created_at, updated_at FROM attribute_list');
        $this->addSql('INSERT INTO profile_item (id, profile_id, definition_id, type, key, options, position) SELECT id, list_id, definition_id, type, key, options, position FROM attribute_list_item');

        $this->addSql('DROP TABLE attribute_list');
        $this->addSql('DROP TABLE attribute_list_item');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attribute_list (id UUID NOT NULL, title VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, public BOOLEAN NOT NULL, owner_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN attribute_list.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_list.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN attribute_list.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE attribute_list_item (id UUID NOT NULL, list_id UUID NOT NULL, definition_id UUID DEFAULT NULL, type SMALLINT NOT NULL, key VARCHAR(150) DEFAULT NULL, options JSON DEFAULT NULL, "position" INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_e7f48a713dae168b ON attribute_list_item (list_id)');
        $this->addSql('CREATE INDEX idx_e7f48a71d11ea911 ON attribute_list_item (definition_id)');
        $this->addSql('CREATE UNIQUE INDEX list_def_uniq ON attribute_list_item (list_id, definition_id, key, type)');
        $this->addSql('COMMENT ON COLUMN attribute_list_item.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_list_item.list_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN attribute_list_item.definition_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE attribute_list_item ADD CONSTRAINT fk_e7f48a713dae168b FOREIGN KEY (list_id) REFERENCES attribute_list (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribute_list_item ADD CONSTRAINT fk_e7f48a71d11ea911 FOREIGN KEY (definition_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE profile_item DROP CONSTRAINT FK_A9D3AA8ACCFA12B8');
        $this->addSql('ALTER TABLE profile_item DROP CONSTRAINT FK_A9D3AA8AD11EA911');

        $this->addSql('INSERT INTO attribute_list (id, title, description, public, owner_id, created_at, updated_at) SELECT id, title, description, public, owner_id, created_at, updated_at FROM profile');
        $this->addSql('INSERT INTO attribute_list_item (id, list_id, definition_id, type, key, options, position) SELECT id, profile_id, definition_id, type, key, options, position FROM profile_item');

        $this->addSql('DROP TABLE profile');
        $this->addSql('DROP TABLE profile_item');
    }
}
