<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415170348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile data entity and relation to profile, add section field to profile item and update unique index';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE profile_data (id UUID NOT NULL, profile_id UUID DEFAULT NULL, data JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1B3B7CF7CCFA12B8 ON profile_data (profile_id)');
        $this->addSql('COMMENT ON COLUMN profile_data.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN profile_data.profile_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE profile_data ADD CONSTRAINT FK_1B3B7CF7CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE profile ADD data_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN profile.data_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0F37F5A13C FOREIGN KEY (data_id) REFERENCES profile_data (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8157AA0F37F5A13C ON profile (data_id)');
        $this->addSql('DROP INDEX profile_def_uniq');
        $this->addSql('ALTER TABLE profile_item ADD section SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE profile_item ALTER section DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX profile_def_uniq ON profile_item (profile_id, section, definition_id, key, type)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE profile DROP CONSTRAINT FK_8157AA0F37F5A13C');
        $this->addSql('ALTER TABLE profile_data DROP CONSTRAINT FK_1B3B7CF7CCFA12B8');
        $this->addSql('DROP TABLE profile_data');
        $this->addSql('DROP INDEX UNIQ_8157AA0F37F5A13C');
        $this->addSql('ALTER TABLE profile DROP data_id');
        $this->addSql('DROP INDEX profile_def_uniq');
        $this->addSql('ALTER TABLE profile_item DROP section');
        $this->addSql('CREATE UNIQUE INDEX profile_def_uniq ON profile_item (profile_id, definition_id, key, type)');
    }
}
