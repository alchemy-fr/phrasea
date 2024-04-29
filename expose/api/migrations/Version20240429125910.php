<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240429125910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE failed_event');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE failed_event (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(150) NOT NULL, payload JSON NOT NULL, error TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN failed_event.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN failed_event.created_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
