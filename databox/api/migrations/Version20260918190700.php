<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918190700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use PostgreSQL inet for IP fields in changelog and action log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE action_log ALTER COLUMN ip TYPE INET USING regexp_replace(lower(ip), '([0-9a-f]{4})(?=[0-9a-f])', '\\1:', 'g')::inet");
        $this->addSql("ALTER TABLE change_log ALTER COLUMN ip TYPE INET USING regexp_replace(lower(ip), '([0-9a-f]{4})(?=[0-9a-f])', '\\1:', 'g')::inet");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE action_log ALTER COLUMN ip TYPE VARCHAR(32) USING regexp_replace(lower(host(ip)), '([0-9a-f]{4}):(?=[0-9a-f])', '\\1', 'g')");
        $this->addSql("ALTER TABLE change_log ALTER COLUMN ip TYPE VARCHAR(32) USING regexp_replace(lower(host(ip)), '([0-9a-f]{4}):(?=[0-9a-f])', '\\1', 'g')");
    }
}
