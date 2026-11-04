<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831135816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the notifier_broadcast history table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notifier_broadcast (id UUID NOT NULL, topic VARCHAR(255) NOT NULL, payload JSON NOT NULL, channels JSON DEFAULT NULL, directory VARCHAR(50) NOT NULL, initiator_user_id UUID DEFAULT NULL, exclude_user_id UUID DEFAULT NULL, delivered_count INT NOT NULL, failed_count INT NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_notifier_broadcast_created ON notifier_broadcast (created_at)');
        $this->addSql('CREATE INDEX idx_notifier_broadcast_initiator ON notifier_broadcast (initiator_user_id)');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notifier_broadcast');
    }
}
