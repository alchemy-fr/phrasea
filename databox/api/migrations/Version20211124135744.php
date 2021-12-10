<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211124135744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sdr_allowed RENAME COLUMN sub_definition_rule_id TO rendition_rule_id');
        $this->addSql('ALTER TABLE sdr_allowed RENAME COLUMN sub_definition_class_id TO rendition_class_id');
        $this->addSql('CREATE INDEX IDX_E8839A3254995832 ON sdr_allowed (rendition_rule_id)');
        $this->addSql('CREATE INDEX IDX_E8839A323E9BECA9 ON sdr_allowed (rendition_class_id)');
    }

    public function down(Schema $schema): void
    {
    }
}
