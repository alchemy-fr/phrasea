<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619131954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE workspace_integration ADD owner_id VARCHAR(36) NULL');
        $this->addSql('UPDATE workspace_integration SET owner_id = (SELECT owner_id FROM workspace WHERE id = workspace_integration.workspace_id) WHERE workspace_integration.owner_id IS NULL');
        $this->addSql('UPDATE workspace_integration SET owner_id = \'unkown\' WHERE owner_id IS NULL');
        $this->addSql('ALTER TABLE workspace_integration ALTER owner_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE workspace_integration DROP owner_id');
    }
}
