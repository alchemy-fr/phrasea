<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The notifier user id columns moved from Types::GUID to the ramsey uuid type
 * (databox: Version20260923170509): add the Doctrine type comment hint.
 */
final class Version20260923170511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Doctrine type comments on the notifier user id columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifier_broadcast ALTER initiator_user_id TYPE UUID');
        $this->addSql('ALTER TABLE notifier_broadcast ALTER exclude_user_id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.initiator_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.exclude_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE notifier_subscriber ALTER user_id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN notifier_subscriber.user_id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN notifier_subscriber.user_id IS NULL');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.initiator_user_id IS NULL');
        $this->addSql('COMMENT ON COLUMN notifier_broadcast.exclude_user_id IS NULL');
    }
}
