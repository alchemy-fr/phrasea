<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123175517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rendition definitions built before the others by the workers (rendition_definition.build_first), set on the thumbnails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendition_definition ADD build_first BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('UPDATE rendition_definition SET build_first = true WHERE use_as_thumbnail = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendition_definition DROP build_first');
    }
}
