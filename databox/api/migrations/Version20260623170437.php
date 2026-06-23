<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260623170437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop asset_index_pass table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE asset_index_pass');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE asset_index_pass (id UUID NOT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, document_count BIGINT NOT NULL, progress BIGINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, estimated VARCHAR(255) DEFAULT NULL, remaining VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN asset_index_pass.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_index_pass.ended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_index_pass.created_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
