<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801143708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove subject and content columns from notifier_notification table and rename data column to payload';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifier_notification DROP subject');
        $this->addSql('ALTER TABLE notifier_notification DROP content');
        $this->addSql('ALTER TABLE notifier_notification RENAME COLUMN data TO payload');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifier_notification ADD subject VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE notifier_notification ADD content TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE notifier_notification RENAME COLUMN payload TO data');
    }
}
