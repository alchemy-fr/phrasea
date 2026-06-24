<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617115716 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invalid column to attribute table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute ADD invalid BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE attribute ALTER invalid DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute DROP invalid');
    }
}
