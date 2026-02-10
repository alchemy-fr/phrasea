<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210152654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cascade delete to asset_attachment.attachment_id foreign key';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_attachment DROP CONSTRAINT FK_BFBE3AE1464E68B');
        $this->addSql('ALTER TABLE asset_attachment ADD CONSTRAINT FK_BFBE3AE1464E68B FOREIGN KEY (attachment_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE asset_attachment DROP CONSTRAINT fk_bfbe3ae1464e68b');
        $this->addSql('ALTER TABLE asset_attachment ADD CONSTRAINT fk_bfbe3ae1464e68b FOREIGN KEY (attachment_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
