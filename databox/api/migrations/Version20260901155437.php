<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260901155437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Support dynamic renditions: nullable definition, custom name and inline build specification on asset_rendition';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_rendition ADD name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_rendition ADD build_definition TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_rendition ADD build_options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_rendition ALTER definition_id DROP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_dynamic_rendition_name ON asset_rendition (asset_id, name) WHERE (definition_id IS NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_dynamic_rendition_name');
        $this->addSql('ALTER TABLE asset_rendition DROP name');
        $this->addSql('ALTER TABLE asset_rendition DROP build_definition');
        $this->addSql('ALTER TABLE asset_rendition DROP build_options');
        $this->addSql('ALTER TABLE asset_rendition ALTER definition_id SET NOT NULL');
    }
}
