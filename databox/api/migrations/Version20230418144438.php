<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230418144438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE attribute_definition SET initializers = null');
        $this->addSql('ALTER TABLE attribute_definition ALTER initializers TYPE JSON USING initializers::json');
        $this->addSql('COMMENT ON COLUMN attribute_definition.initializers IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_definition ALTER initializers TYPE TEXT');
        $this->addSql('COMMENT ON COLUMN attribute_definition.initializers IS \'(DC2Type:array)\'');
    }
}
