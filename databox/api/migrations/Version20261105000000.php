<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105000000 extends AbstractMigration
{
    private const array INDEXES = [
        'idx_b2c5f685aa9e377a' => 'IDX_F6E1C0F5AA9E377A',
        'idx_b2c5f685a76ed395' => 'IDX_F6E1C0F5A76ED395',
        'idx_b2c5f685d1107cff' => 'IDX_F6E1C0F5D1107CFF',
        'idx_b2c5f68547cc8c92' => 'IDX_F6E1C0F547CC8C92',
        'idx_b2c5f68511cb6b3a232d562b' => 'IDX_F6E1C0F511CB6B3A232D562B',
    ];

    public function getDescription(): string
    {
        return 'action_log becomes audit_log: actor type, OAuth client, username, workspace, object label and correlation ID';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_log RENAME TO audit_log');
        $this->addSql('ALTER INDEX action_log_pkey RENAME TO audit_log_pkey');
        foreach (self::INDEXES as $from => $to) {
            $this->addSql(sprintf('ALTER INDEX %s RENAME TO %s', $from, $to));
        }

        // The existing entries were all written for authenticated users
        $this->addSql('ALTER TABLE audit_log ADD actor_type SMALLINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE audit_log ALTER actor_type DROP DEFAULT');
        $this->addSql('ALTER TABLE audit_log ADD client_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_log ADD user_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_log ADD object_label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_log ADD workspace_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_log ADD correlation_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_F6E1C0F582D40A1F ON audit_log (workspace_id)');
        $this->addSql('CREATE INDEX IDX_F6E1C0F59924E382 ON audit_log (correlation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_F6E1C0F582D40A1F');
        $this->addSql('DROP INDEX IDX_F6E1C0F59924E382');
        $this->addSql('ALTER TABLE audit_log DROP actor_type');
        $this->addSql('ALTER TABLE audit_log DROP client_id');
        $this->addSql('ALTER TABLE audit_log DROP user_name');
        $this->addSql('ALTER TABLE audit_log DROP object_label');
        $this->addSql('ALTER TABLE audit_log DROP workspace_id');
        $this->addSql('ALTER TABLE audit_log DROP correlation_id');

        foreach (self::INDEXES as $from => $to) {
            $this->addSql(sprintf('ALTER INDEX %s RENAME TO %s', $to, $from));
        }
        $this->addSql('ALTER INDEX audit_log_pkey RENAME TO action_log_pkey');
        $this->addSql('ALTER TABLE audit_log RENAME TO action_log');
    }
}
