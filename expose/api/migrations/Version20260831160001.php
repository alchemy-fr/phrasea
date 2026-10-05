<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831160001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the notifier_digest buffer table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notifier_digest (id UUID NOT NULL, subscriber_id UUID NOT NULL, topic VARCHAR(255) NOT NULL, channel VARCHAR(20) NOT NULL, events JSON NOT NULL, event_count INT NOT NULL, first_event_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_event_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_693E9CAD7808B1AD ON notifier_digest (subscriber_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifier_digest_bucket ON notifier_digest (subscriber_id, topic, channel)');
        $this->addSql('CREATE INDEX idx_notifier_digest_last_event ON notifier_digest (last_event_at)');
        $this->addSql('COMMENT ON COLUMN notifier_digest.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_digest.subscriber_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_digest.first_event_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifier_digest.last_event_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifier_digest.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE notifier_digest ADD CONSTRAINT FK_693E9CAD7808B1AD FOREIGN KEY (subscriber_id) REFERENCES notifier_subscriber (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifier_digest DROP CONSTRAINT FK_693E9CAD7808B1AD');
        $this->addSql('DROP TABLE notifier_digest');
    }
}
