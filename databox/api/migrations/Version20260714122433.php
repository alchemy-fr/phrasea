<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714122433 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change user_id and impersonator_id columns to VARCHAR(255) and add indexes on action_log and change_log tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_log ALTER user_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE action_log ALTER impersonator_id TYPE VARCHAR(255)');
        $this->addSql('CREATE INDEX IDX_B2C5F685AA9E377A ON action_log (date)');
        $this->addSql('CREATE INDEX IDX_B2C5F685A76ED395 ON action_log (user_id)');
        $this->addSql('CREATE INDEX IDX_B2C5F685D1107CFF ON action_log (impersonator_id)');
        $this->addSql('CREATE INDEX IDX_B2C5F68547CC8C92 ON action_log (action)');
        $this->addSql('CREATE INDEX IDX_B2C5F68511CB6B3A232D562B ON action_log (object_type, object_id)');
        $this->addSql('ALTER TABLE change_log ALTER user_id TYPE VARCHAR(255)');
        $this->addSql('CREATE INDEX IDX_5E1A0AD8AA9E377A ON change_log (date)');
        $this->addSql('CREATE INDEX IDX_5E1A0AD8A76ED395 ON change_log (user_id)');
        $this->addSql('CREATE INDEX IDX_5E1A0AD847CC8C92 ON change_log (action)');
        $this->addSql('CREATE INDEX IDX_5E1A0AD811CB6B3A232D562B ON change_log (object_type, object_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_B2C5F685AA9E377A');
        $this->addSql('DROP INDEX IDX_B2C5F685A76ED395');
        $this->addSql('DROP INDEX IDX_B2C5F685D1107CFF');
        $this->addSql('DROP INDEX IDX_B2C5F68547CC8C92');
        $this->addSql('DROP INDEX IDX_B2C5F68511CB6B3A232D562B');
        $this->addSql('ALTER TABLE action_log ALTER user_id TYPE VARCHAR(36)');
        $this->addSql('ALTER TABLE action_log ALTER impersonator_id TYPE VARCHAR(36)');
        $this->addSql('DROP INDEX IDX_5E1A0AD8AA9E377A');
        $this->addSql('DROP INDEX IDX_5E1A0AD8A76ED395');
        $this->addSql('DROP INDEX IDX_5E1A0AD847CC8C92');
        $this->addSql('DROP INDEX IDX_5E1A0AD811CB6B3A232D562B');
        $this->addSql('ALTER TABLE change_log ALTER user_id TYPE VARCHAR(36)');
    }
}
