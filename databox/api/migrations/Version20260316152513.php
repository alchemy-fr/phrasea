<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316152513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add editable field to rendition policy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendition_policy ADD editable BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('UPDATE rendition_policy SET editable = true WHERE public = true');
        $this->addSql('ALTER TABLE rendition_policy ALTER editable DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rendition_policy DROP editable');
    }
}
