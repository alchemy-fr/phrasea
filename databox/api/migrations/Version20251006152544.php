<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251006152544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rendition_definition RENAME use_as_original TO use_as_main');
        $this->addSql('ALTER TABLE rendition_definition RENAME use_as_thumbnail_active TO use_as_animated_thumbnail');
    }

    public function down(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rendition_definition RENAME use_as_main TO use_as_original');
        $this->addSql('ALTER TABLE rendition_definition RENAME use_as_animated_thumbnail TO use_as_thumbnail_active');
    }
}
