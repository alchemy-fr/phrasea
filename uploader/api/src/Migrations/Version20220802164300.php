<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220802164300 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE asset ALTER target_id SET NOT NULL');
        $this->addSql('ALTER TABLE asset_commit ALTER target_id SET NOT NULL');
        $this->addSql('ALTER TABLE bulk_data ALTER target_id SET NOT NULL');
        $this->addSql('ALTER TABLE form_schema ALTER target_id SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE bulk_data ALTER target_id DROP NOT NULL');
        $this->addSql('ALTER TABLE form_schema ALTER target_id DROP NOT NULL');
        $this->addSql('ALTER TABLE asset_commit ALTER target_id DROP NOT NULL');
        $this->addSql('ALTER TABLE asset ALTER target_id DROP NOT NULL');
    }
}
