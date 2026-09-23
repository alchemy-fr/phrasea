<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260923170509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change GUID columns to UUID columns';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifier_broadcast ALTER initiator_user_id TYPE UUID');
        $this->addSql('ALTER TABLE notifier_broadcast ALTER exclude_user_id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.initiator_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.exclude_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE notifier_subscriber ALTER user_id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN notifier_subscriber.user_id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifier_subscriber ALTER user_id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN notifier_subscriber.user_id IS NULL');
        $this->addSql('ALTER TABLE notifier_broadcast ALTER initiator_user_id TYPE UUID');
        $this->addSql('ALTER TABLE notifier_broadcast ALTER exclude_user_id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.initiator_user_id IS NULL');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.exclude_user_id IS NULL');
    }
}
