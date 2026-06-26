<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260622080201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invalid column to template_attribute table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE template_attribute ADD invalid BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE template_attribute ALTER invalid DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE template_attribute DROP invalid');
    }
}
