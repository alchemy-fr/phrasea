<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609120204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external_id to AttributeEntity and unique index on list_id and external_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_entity ADD external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX list_external_id_uniq ON attribute_entity (list_id, external_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX list_external_id_uniq');
        $this->addSql('ALTER TABLE attribute_entity DROP external_id');
    }
}
