<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'image_set.source_attribute_id: the face an image was cropped from (null for an uploaded image)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image_set ADD source_attribute_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image_set DROP source_attribute_id');
    }
}
