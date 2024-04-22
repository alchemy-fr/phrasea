<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201209130408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE INDEX user_idx ON access_control_entry (user_type, user_id)');
        $this->addSql('CREATE INDEX object_idx ON access_control_entry (object_type, object_id)');
        $this->addSql('CREATE INDEX user_type_idx ON access_control_entry (user_type)');
        $this->addSql('CREATE INDEX object_type_idx ON access_control_entry (object_type)');
        $this->addSql('ALTER TABLE download_request DROP CONSTRAINT FK_97E98E5A38B217A7');
        $this->addSql('ALTER TABLE download_request ADD CONSTRAINT FK_97E98E5A38B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE download_request DROP CONSTRAINT fk_97e98e5a38b217a7');
        $this->addSql('ALTER TABLE download_request ADD CONSTRAINT fk_97e98e5a38b217a7 FOREIGN KEY (publication_id) REFERENCES publication (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX user_idx');
        $this->addSql('DROP INDEX object_idx');
        $this->addSql('DROP INDEX user_type_idx');
        $this->addSql('DROP INDEX object_type_idx');
    }
}
