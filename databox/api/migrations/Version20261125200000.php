<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the workflow states by start date and by status (admin home page)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX workflow_state_started_at ON workflow_state (started_at)');
        $this->addSql('CREATE INDEX workflow_state_status_started_at ON workflow_state (status, started_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX workflow_state_started_at');
        $this->addSql('DROP INDEX workflow_state_status_started_at');
    }
}
