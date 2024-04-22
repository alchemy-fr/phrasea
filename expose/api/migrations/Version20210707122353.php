<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210707122353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE asset ADD client_annotations TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD client_annotations TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication_asset ADD client_annotations TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication_profile ADD client_annotations TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset DROP client_annotations');
        $this->addSql('ALTER TABLE publication DROP client_annotations');
        $this->addSql('ALTER TABLE publication_asset DROP client_annotations');
        $this->addSql('ALTER TABLE publication_profile DROP client_annotations');
    }
}
