<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shares may narrow down the renditions they deliver (share_rendition_definition join table, share.renditions_restricted)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE share_rendition_definition (share_id UUID NOT NULL, rendition_definition_id UUID NOT NULL, PRIMARY KEY (share_id, rendition_definition_id))');
        $this->addSql('CREATE INDEX IDX_BFCE2C442AE63FDB ON share_rendition_definition (share_id)');
        $this->addSql('CREATE INDEX IDX_BFCE2C449373EDFF ON share_rendition_definition (rendition_definition_id)');
        $this->addSql('ALTER TABLE share_rendition_definition ADD CONSTRAINT FK_BFCE2C442AE63FDB FOREIGN KEY (share_id) REFERENCES share (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE share_rendition_definition ADD CONSTRAINT FK_BFCE2C449373EDFF FOREIGN KEY (rendition_definition_id) REFERENCES rendition_definition (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE share ADD renditions_restricted BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE share DROP renditions_restricted');
        $this->addSql('ALTER TABLE share_rendition_definition DROP CONSTRAINT FK_BFCE2C442AE63FDB');
        $this->addSql('ALTER TABLE share_rendition_definition DROP CONSTRAINT FK_BFCE2C449373EDFF');
        $this->addSql('DROP TABLE share_rendition_definition');
    }
}
