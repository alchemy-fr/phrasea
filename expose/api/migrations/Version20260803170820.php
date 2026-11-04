<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803170820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change user_id column type to uuid in notifier_subscriber table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifier_subscriber ALTER user_id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN notifier_subscriber.user_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifier_subscriber ALTER user_id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN notifier_subscriber.user_id IS \'(DC2Type:uuid)\'');
    }
}
