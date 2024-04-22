<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220802163100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('UPDATE asset_commit SET target_id = (SELECT id FROM target WHERE name = \'Default\')');
        $this->addSql('UPDATE asset SET target_id = (SELECT id FROM target WHERE name = \'Default\')');
        $this->addSql('UPDATE form_schema SET target_id = (SELECT id FROM target WHERE name = \'Default\')');
        $this->addSql('UPDATE bulk_data SET target_id = (SELECT id FROM target WHERE name = \'Default\')');
        $this->addSql('ALTER TABLE target ALTER target_access_token DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE target ALTER target_access_token SET NOT NULL');
    }
}
