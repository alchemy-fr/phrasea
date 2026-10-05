<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803170229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notifier tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notifier_notification (id UUID NOT NULL, subscriber_id UUID NOT NULL, topic VARCHAR(255) NOT NULL, payload JSON NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8C3FE0797808B1AD ON notifier_notification (subscriber_id)');
        $this->addSql('CREATE INDEX idx_notifier_notification_subscriber ON notifier_notification (subscriber_id, created_at)');
        $this->addSql('COMMENT ON COLUMN notifier_notification.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_notification.subscriber_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_notification.read_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifier_notification.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE notifier_preference (id UUID NOT NULL, subscriber_id UUID NOT NULL, topic VARCHAR(255) NOT NULL, channel VARCHAR(20) NOT NULL, enabled BOOLEAN NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B8C25817808B1AD ON notifier_preference (subscriber_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifier_preference ON notifier_preference (subscriber_id, topic, channel)');
        $this->addSql('COMMENT ON COLUMN notifier_preference.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_preference.subscriber_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_preference.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE notifier_subscriber (id UUID NOT NULL, user_id UUID NOT NULL, email VARCHAR(255) DEFAULT NULL, phone_number VARCHAR(30) DEFAULT NULL, locale VARCHAR(10) DEFAULT NULL, display_name VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifier_subscriber_user ON notifier_subscriber (user_id)');
        $this->addSql('COMMENT ON COLUMN notifier_subscriber.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_subscriber.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifier_subscriber.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE notifier_subscription (id UUID NOT NULL, subscriber_id UUID NOT NULL, event VARCHAR(100) NOT NULL, object_type VARCHAR(30) DEFAULT NULL, object_id VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_90ADF2607808B1AD ON notifier_subscription (subscriber_id)');
        $this->addSql('CREATE INDEX idx_notifier_subscription_event ON notifier_subscription (event)');
        $this->addSql('CREATE INDEX idx_notifier_subscription_event_object ON notifier_subscription (event, object_type, object_id)');
        $this->addSql('CREATE INDEX idx_notifier_subscription_object ON notifier_subscription (object_type, object_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifier_subscription ON notifier_subscription (subscriber_id, event, object_type, object_id)');
        $this->addSql('COMMENT ON COLUMN notifier_subscription.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_subscription.subscriber_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_subscription.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE notifier_notification ADD CONSTRAINT FK_8C3FE0797808B1AD FOREIGN KEY (subscriber_id) REFERENCES notifier_subscriber (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notifier_preference ADD CONSTRAINT FK_B8C25817808B1AD FOREIGN KEY (subscriber_id) REFERENCES notifier_subscriber (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notifier_subscription ADD CONSTRAINT FK_90ADF2607808B1AD FOREIGN KEY (subscriber_id) REFERENCES notifier_subscriber (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifier_notification DROP CONSTRAINT FK_8C3FE0797808B1AD');
        $this->addSql('ALTER TABLE notifier_preference DROP CONSTRAINT FK_B8C25817808B1AD');
        $this->addSql('ALTER TABLE notifier_subscription DROP CONSTRAINT FK_90ADF2607808B1AD');
        $this->addSql('DROP TABLE notifier_notification');
        $this->addSql('DROP TABLE notifier_preference');
        $this->addSql('DROP TABLE notifier_subscriber');
        $this->addSql('DROP TABLE notifier_subscription');
    }
}
