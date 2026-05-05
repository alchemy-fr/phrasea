<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428153126 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status field to attribute_entity and options field to entity_list';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity ADD status SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE attribute_entity ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE entity_list ADD options JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute_entity DROP status');
        $this->addSql('ALTER TABLE entity_list DROP options');
    }
}
