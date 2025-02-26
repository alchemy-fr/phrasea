<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250218171621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add workspace templates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE workspace_template (id UUID NOT NULL, name VARCHAR(255) NOT NULL, data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('COMMENT ON COLUMN workspace_template.id IS \'(DC2Type:uuid)\';');
        $this->addSql('COMMENT ON COLUMN workspace_template.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN workspace_template.updated_at IS \'(DC2Type:datetime_immutable)\';');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE workspace_template');
    }
}
