<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220413141940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webhook_log ADD webhook_id UUID NOT NULL');
        $this->addSql('COMMENT ON COLUMN webhook_log.webhook_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE webhook_log ADD CONSTRAINT FK_736542785C9BA60B FOREIGN KEY (webhook_id) REFERENCES webhook (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_736542785C9BA60B ON webhook_log (webhook_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webhook_log DROP CONSTRAINT FK_736542785C9BA60B');
        $this->addSql('DROP INDEX IDX_736542785C9BA60B');
        $this->addSql('ALTER TABLE webhook_log DROP webhook_id');
    }
}
