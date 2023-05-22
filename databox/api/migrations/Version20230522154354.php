<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230522154354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE workspace_integration_workspace_integration (workspace_integration_source UUID NOT NULL, workspace_integration_target UUID NOT NULL, PRIMARY KEY(workspace_integration_source, workspace_integration_target))');
        $this->addSql('CREATE INDEX IDX_5362B98B4871F688 ON workspace_integration_workspace_integration (workspace_integration_source)');
        $this->addSql('CREATE INDEX IDX_5362B98B5194A607 ON workspace_integration_workspace_integration (workspace_integration_target)');
        $this->addSql('COMMENT ON COLUMN workspace_integration_workspace_integration.workspace_integration_source IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN workspace_integration_workspace_integration.workspace_integration_target IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE workspace_integration_workspace_integration ADD CONSTRAINT FK_5362B98B4871F688 FOREIGN KEY (workspace_integration_source) REFERENCES workspace_integration (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE workspace_integration_workspace_integration ADD CONSTRAINT FK_5362B98B5194A607 FOREIGN KEY (workspace_integration_target) REFERENCES workspace_integration (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE workspace_integration_workspace_integration DROP CONSTRAINT FK_5362B98B4871F688');
        $this->addSql('ALTER TABLE workspace_integration_workspace_integration DROP CONSTRAINT FK_5362B98B5194A607');
        $this->addSql('DROP TABLE workspace_integration_workspace_integration');
    }
}
