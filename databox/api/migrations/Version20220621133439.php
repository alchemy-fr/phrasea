<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220621133439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_class ADD key VARCHAR(150) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_class_ws_key ON attribute_class (workspace_id, key)');
        $this->addSql('ALTER INDEX attr_class_uniq RENAME TO uniq_class_ws_name');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX uniq_class_ws_key');
        $this->addSql('ALTER TABLE attribute_class DROP key');
        $this->addSql('ALTER INDEX uniq_class_ws_name RENAME TO attr_class_uniq');
    }
}
