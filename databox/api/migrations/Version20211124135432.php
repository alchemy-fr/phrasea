<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211124135432 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sub_definition_class RENAME TO rendition_class');
        $this->addSql('ALTER TABLE sub_definition RENAME TO asset_rendition');
        $this->addSql('ALTER TABLE sub_definition_rule RENAME TO rendition_rule');
        $this->addSql('ALTER TABLE sub_definition_spec RENAME TO rendition_definition');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendition_class RENAME TO sub_definition_class');
        $this->addSql('ALTER TABLE asset_rendition RENAME TO sub_definition');
        $this->addSql('ALTER TABLE rendition_rule RENAME TO sub_definition_rule');
        $this->addSql('ALTER TABLE rendition_definition RENAME TO sub_definition_spec');
    }
}
