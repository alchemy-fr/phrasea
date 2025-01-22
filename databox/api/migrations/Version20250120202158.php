<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250120202158 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove topic key from thread';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_31204c83b933da0b');
        $this->addSql('ALTER TABLE thread DROP topic_key');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE thread ADD topic_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_31204c83b933da0b ON thread (topic_key)');
    }
}
