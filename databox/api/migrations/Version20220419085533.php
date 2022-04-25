<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220419085533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webhook_log DROP CONSTRAINT FK_736542785C9BA60B');
        $this->addSql('ALTER TABLE webhook_log ADD CONSTRAINT FK_736542785C9BA60B FOREIGN KEY (webhook_id) REFERENCES webhook (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE webhook_log DROP CONSTRAINT fk_736542785c9ba60b');
        $this->addSql('ALTER TABLE webhook_log ADD CONSTRAINT fk_736542785c9ba60b FOREIGN KEY (webhook_id) REFERENCES webhook (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
